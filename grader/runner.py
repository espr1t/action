import os
from dataclasses import dataclass, field
from signal import SIGKILL, SIGTERM

import config
import common
from common import TestStatus
from tempfile import TemporaryFile
from sandbox import Sandbox


logger = common.get_logger(__file__)


@dataclass
class RunResult:
    status: TestStatus = TestStatus.UNKNOWN
    exit_code: int = 0
    exec_time: float = 0.0
    exec_memory: int = 0
    score: float = 0.0
    output: bytes = None
    info: str = ""
    error: str = ""


@dataclass
class RunConfig:
    time_limit: float
    memory_limit: int
    executable_path: str
    tester_path: str = None
    checker_path: str = None
    compare_floats: bool = False
    timeout: float = field(init=False)

    def __post_init__(self):
        # Terminate run after TL + 0.2s or TL + 20% (whichever larger)
        self.timeout = self.time_limit + max(0.2, self.time_limit * 0.2)


"""
class RunConfig:
    def __init__(self, time_limit, memory_limit, executable_path,
                 tester_path=None, checker_path=None, compare_floats=False):
        self.time_limit = time_limit
        self.memory_limit = memory_limit
        self.executable_path = executable_path
        self.tester_path = tester_path
        self.checker_path = checker_path
        self.compare_floats = compare_floats

        # self.executable_name = os.path.basename(self.executable_path)
        # self.executable_language = Runner.get_language_by_exec_name(self.executable_name)
        #
        # if self.tester_path is not None:
        #     self.tester_name = os.path.basename(self.tester_path)
        #     self.tester_language = Runner.get_language_by_exec_name(self.tester_path)
        #
        # if self.checker_path is not None:
        #     self.checker_name = os.path.basename(self.checker_path)
        #     self.checker_language = Runner.get_language_by_exec_name(self.checker_path)
        #
        # # Determine actual time and memory limits
        # # (this accounts for JVM startup time and memory overhead)
        # self.time_offset = Runner.get_time_offset(self.executable_language)
        # self.memory_offset = Runner.get_memory_offset(self.executable_language)

        # Terminate after TL + 0.2s or TL + 20% (whichever larger)
        self.timeout = self.time_limit + max(0.2, self.time_limit * 0.2)
"""


class Runner:
    # Redirect the run_command's stderr to /dev/null, but keep the output from /usr/bin/time (which is also stderr)
    # http://man7.org/linux/man-pages/man1/time.1.html
    # --format argument '%x' prints "Exit status of the command."
    # --format argument '%U' prints "Elapsed CPU seconds in User mode"
    # --format argument '%S' prints "Total number of CPU-seconds that the process spent in kernel mode."
    # --format argument '%e' prints "Elapsed real (clock) time in seconds"
    # --format argument '%M' prints "Maximum resident set size (kbytes)"
    # Send a SIGTERM signal after {timeout} seconds, but ensure the program is killed after 0.2 more seconds
    COMMAND_WRAPPER = \
        "{time_cmd} /bin/bash -c \"{timeout_cmd} /bin/bash -c \\\"{{command}}\\\" 2>/dev/null\" ; >&2 echo $?".format(
            time_cmd="/usr/bin/time --quiet --format='%x %U %S %e %M'",
            timeout_cmd="/usr/bin/timeout --preserve-status --kill-after=0.2s --signal=SIGTERM {timeout}s"
        )

    @staticmethod
    def parse_exec_info(info_lines: [str], timeout: float):
        """
        Parses the output of the above command for exit_code, used time and memory
        :param info_lines: Two strings: the output by the /usr/bin/time in the first, and the exit code in the second
        :param timeout: A float, the timeout used in the above command
        :return: A tuple (exit_code, exec_time, exec_memory)
        """
        print("/usr/bin/time output: {}".format(info_lines))

        if len(info_lines) != 2:
            logger.error("Expected info_lines to contain two strings; got {}".format(len(info_lines)))
            return -1, -1, -1

        # Fix exit code (it is offset by 128 by /usr/bin/timeout command)
        exit_code = int(info_lines[1]) % 128

        # Exec time is user time + sys time
        tokens = info_lines[0].strip().split()
        exec_time = float(tokens[1]) + float(tokens[2])
        clock_time = float(tokens[3])
        exec_memory = int(tokens[4]) * 1024

        # If the program was killed, but the user+sys time is small, use clock time instead
        # This can happen if the program sleeps, for example, or blocks on a resource it never gets
        if exit_code == SIGKILL or exit_code == SIGTERM:
            if exec_time < timeout:
                exec_time = clock_time

        # Return the exit code, execution time and execution memory as a tuple
        return exit_code, exec_time, exec_memory

    @staticmethod
    def get_language_by_exec_name(executable_name):
        if executable_name.endswith(config.EXECUTABLE_EXTENSION_CPP):
            return config.LANGUAGE_CPP
        elif executable_name.endswith(config.EXECUTABLE_EXTENSION_JAVA):
            return config.LANGUAGE_JAVA
        elif executable_name.endswith(config.EXECUTABLE_EXTENSION_PYTHON):
            return config.LANGUAGE_PYTHON
        raise Exception("Could not determine language for executable '{}'!".format(executable_name))

    @staticmethod
    def get_time_offset(language):
        if language == config.LANGUAGE_CPP:
            return config.TIME_OFFSET_CPP
        if language == config.LANGUAGE_JAVA:
            return config.TIME_OFFSET_JAVA
        if language == config.LANGUAGE_PYTHON:
            return config.TIME_OFFSET_PYTHON
        raise Exception("Unsupported language '{}'!".format(language))

    @staticmethod
    def get_memory_offset(language):
        if language == config.LANGUAGE_CPP:
            return config.MEMORY_OFFSET_CPP
        if language == config.LANGUAGE_JAVA:
            return config.MEMORY_OFFSET_JAVA
        if language == config.LANGUAGE_PYTHON:
            return config.MEMORY_OFFSET_PYTHON
        raise Exception("Unsupported language '{}'!".format(language))

    @staticmethod
    def get_run_command(language, executable, memory_limit):
        if language == config.LANGUAGE_CPP:
            return "./{executable}".format(executable=executable)
        if language == config.LANGUAGE_JAVA:
            return "java -XX:+UseSerialGC -Xmx{memory_limit_in_mb}m -Xss64m {executable}".format(
                memory_limit_in_mb=memory_limit // 1048576, executable=executable
            )
        if language == config.LANGUAGE_PYTHON:
            return "python3 {executable}".format(executable=executable)
        raise Exception("Unsupported language")

    @staticmethod
    def run(sandbox: Sandbox, command, input_bytes=None, privileged=False) -> (bytes, bytes):
        # Prepare the communication pipes
        stdin, stdout, stderr = TemporaryFile("wb+"), TemporaryFile("wb+"), TemporaryFile("wb+")

        # Write the input (if any) to the stdin
        if input_bytes is not None:
            stdin.write(input_bytes)
            stdin.flush()
            stdin.seek(0)

        # Run the command in the sandboxed environment
        sandbox.execute(
            command=command,
            stdin_fd=stdin,
            stdout_fd=stdout,
            stderr_fd=stderr,
            blocking=True,
            privileged=privileged
        )

        # Store the execution's stdout and stderr
        stdout.seek(0); stdout_bytes = stdout.read()
        stderr.seek(0); stderr_bytes = stderr.read()

        # If running java or javac or jar the JVM prints an annoying message:
        # "Picked up JAVA_TOOL_OPTIONS: <actual options set by sandbox environment>
        # Remove it from the stderr if it is there
        if any(java in command for java in ["java", "javac", "jar"]):
            stdout_bytes = "\n".join(
                [line for line in stdout_bytes.decode().splitlines() if not line.startswith("Picked up JAVA_TOOL_OPTIONS")]
            ).encode()
            stderr_bytes = "\n".join(
                [line for line in stderr_bytes.decode().splitlines() if not line.startswith("Picked up JAVA_TOOL_OPTIONS")]
            ).encode()

        return stdout_bytes, stderr_bytes

    @staticmethod
    def run_command(sandbox: Sandbox, command, timeout,
                input_bytes=None, print_stderr=False, privileged=False) -> RunResult:
        # Wrap the command in a timing and time limiting functions
        command = Runner.COMMAND_WRAPPER.format(command=command, timeout=timeout)

        # Instead of ignoring it, merge the stderr to stdout if requested
        if print_stderr:
            command = command.replace("/dev/null", "&1")

        # Execute the wrapped command and parse the execution info from /usr/bin/time's output
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=sandbox, command=command, input_bytes=input_bytes, privileged=privileged
        )

        info_lines = stderr_bytes.decode(encoding=config.OUTPUT_ENCODING).strip().splitlines()[-2:]
        exit_code, exec_time, exec_memory = Runner.parse_exec_info(info_lines=info_lines, timeout=timeout)
        return RunResult(exit_code=exit_code, exec_time=exec_time, exec_memory=exec_memory, output=stdout_bytes)

    @staticmethod
    def run_program(sandbox: Sandbox, executable_path, memory_limit, timeout,
                input_bytes=None, print_stderr=False, args=None, privileged=False) -> RunResult:
        # Copy the executable to the sandbox directory
        sandbox.put_file(executable_path)

        # Generate the command which runs the executable (it is language-specific)
        executable_name = os.path.basename(executable_path)
        executable_language = Runner.get_language_by_exec_name(executable_name)
        command = Runner.get_run_command(executable_language, executable_name, memory_limit)

        # If the executable depends on arguments, add them to the run command
        if args is not None:
            for arg in args:
                command = command + " " + arg

        # Run the program and measure its time and memory consumption
        run_result = Runner.run_command(
            sandbox=sandbox,
            command=command,
            timeout=timeout,
            input_bytes=input_bytes,
            print_stderr=print_stderr,
            privileged=privileged
        )

        # Calculate final time and memory (offset for VM start-up time)
        run_result.exec_time = max(0, run_result.exec_time - Runner.get_time_offset(executable_language))
        run_result.exec_memory = max(0, run_result.exec_memory - Runner.get_memory_offset(executable_language))

        # The caller function should populate the rest of the fields of the RunResult
        return run_result
