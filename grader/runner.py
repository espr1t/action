import os
from dataclasses import dataclass, field
from signal import SIGKILL
import fcntl

import config
import common
from common import TestStatus
from sandbox import Sandbox
from executors import Executors


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
    # Redirect the run_command's stderr to /dev/null, but keep the output from /time/time (which is also stderr)
    # Send a SIGKILL signal after {timeout} seconds to get the program killed
    COMMAND_WRAPPER = \
        "/time/time \"{timeout_cmd} /bin/bash -c \\\"({{command}}) 2>/dev/null\\\"\"".format(
            timeout_cmd="/usr/bin/timeout --preserve-status --signal=SIGKILL {timeout}s"
        )

    @staticmethod
    def parse_exec_info(info: str, timeout: float):
        """
        Parses the output of custom time command (/time/time) for exit_code, used time and memory
        Command output has the following format:
            > 0 -- exit code
            > 0.021 -- user time (seconds)
            > 0.016 -- sys time (seconds)
            > 0.091 -- clock time (seconds)
            > 26640384 -- max resident set size (bytes)
            > 0 -- shared resident set size (bytes)
            > 0 -- unshared data size (bytes)
            > 0 -- unshared stack size (bytes)
            > 0 -- number of swaps
            > 521 -- voluntary context switches
            > 0 -- involuntary context switches
        :param info: A string containing the stderr output from running the above command
        :param timeout: A float, the timeout used in the above command
        :return: A tuple (exit_code, exec_time, exec_memory) or None
        """

        # print("Parsing output:\n{}".format(info))

        info_lines = info.splitlines()
        # First sanity check: there are enough output lines
        if len(info_lines) < 18:
            logger.error("Expected at least 18 lines of output, got {}".format(len(info_lines)))
            return None
        # Second sanity check: the first of these 18 lines looks the way we expect
        if "-- exit code" not in info_lines[-18]:
            logger.error("Output didn't pass sanity check:\n{}".format("\n".join(info_lines[-18:])))
            return None
        info_values = [line.split()[0] for line in info_lines[-18:]]

        try:
            exit_code = int(info_values[0])
            # Exec time is user time + sys time
            exec_time = round(float(info_values[1]) + float(info_values[2]), 3)
            clock_time = round(float(info_values[3]), 3)
            exec_memory = int(info_values[4])
        except ValueError:
            logger.error("Could not parse exec info from string: {}".format("|".join(info_lines[-2:])))
            return None

        # If the program was killed, but the user+sys time is small, use clock time instead
        # This can happen if the program sleeps, for example, or blocks on a resource it never gets
        if exit_code == SIGKILL and exec_time < timeout:
            exec_time = clock_time

        if abs(exec_time - clock_time) > 0.1 * clock_time:
            logger.warning("Returned execution time differs from clock time by more than 10% ({:.3f}s vs. {:.3f}s)"
                           .format(exec_time, clock_time))

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
            return "java -XX:+UseSerialGC -Xmx{max_memory_mb}m -Xss64m -jar {executable}".format(
                max_memory_mb=(memory_limit + config.MEMORY_OFFSET_JAVA) // 2**20, executable=executable
            )
        if language == config.LANGUAGE_PYTHON:
            return "python3 {executable}".format(executable=executable)
        raise Exception("Unsupported language")

    @staticmethod
    def get_pipe(max_size):
        pipe = os.pipe()
        # Change the pipe buffers to be much larger so writes don't block
        # (it is very easy to get to a deadlock here, and this solves the issue to some extent)
        if not hasattr(fcntl, "F_SETPIPE_SZ"):
            fcntl.F_SETPIPE_SZ = 1031
        fcntl.fcntl(pipe[1], fcntl.F_SETPIPE_SZ, max_size)
        # Change the blocking policy to not block but fail whenever the output limit is exceeded
        fcntl.fcntl(pipe[1], fcntl.F_SETFL, os.O_NONBLOCK)
        return pipe

    @staticmethod
    def run(sandbox: Sandbox, command, input_bytes=None, privileged=False) -> (bytes, bytes):
        # For tasks with large inputs concurrent read/write seems to be a problem.
        # To solve this, prevent other executors to be created (using a lock) until we
        # are done with the test (effectively blocking parallel executions temporarily)
        should_lock = input_bytes is not None and len(input_bytes) > config.CONCURRENT_IO_LIMIT
        if should_lock:
            Executors.lock()

        # Make the input and output go through pipes as they don't require hard drive I/O
        stdin = Runner.get_pipe(max_size=0 if input_bytes is None else len(input_bytes))
        stdout = Runner.get_pipe(max_size=config.MAX_EXECUTION_OUTPUT)
        stderr = Runner.get_pipe(max_size=config.MAX_EXECUTION_OUTPUT)

        # If there is input, write the data into the stdin pipe
        if input_bytes is not None:
            with open(stdin[1], "wb") as inp:
                inp.write(input_bytes)
        else:
            os.close(stdin[1])

        # Run the command in the sandboxed environment
        sandbox.execute(
            command=command,
            stdin_fd=stdin[0],
            stdout_fd=stdout[1],
            stderr_fd=stderr[1],
            blocking=True,
            privileged=privileged
        )
        os.close(stdin[0])

        # Store the execution's stdout and stderr
        os.close(stdout[1])
        with open(stdout[0], "rb") as out:
            stdout_bytes = out.read()
        os.close(stderr[1])
        with open(stderr[0], "rb") as err:
            stderr_bytes = err.read()

        if should_lock:
            Executors.unlock()

        # If running java or javac or jar the JVM prints an annoying message:
        # "Picked up JAVA_TOOL_OPTIONS: <actual options set by sandbox environment>
        # Remove it from the stderr if it is there
        if any(java in command for java in ["java", "javac", "jar"]):
            stdout_bytes = "\n".join(
                [line for line in stdout_bytes.decode().splitlines() if not line.startswith("Picked up JAVA_TOOL_OPT")]
            ).encode()
            stderr_bytes = "\n".join(
                [line for line in stderr_bytes.decode().splitlines() if not line.startswith("Picked up JAVA_TOOL_OPT")]
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
        info = stderr_bytes.decode(encoding=config.OUTPUT_ENCODING).strip()
        exit_code, exec_time, exec_memory = Runner.parse_exec_info(info=info, timeout=timeout)
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
