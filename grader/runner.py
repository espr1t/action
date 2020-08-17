import os
from dataclasses import dataclass, field
import fcntl

import config
import common
from sandbox import Sandbox
from wrapper import COMMAND_WRAPPER, parse_exec_info


logger = common.get_logger(__file__)


@dataclass
class RunResult:
    status: common.TestStatus = common.TestStatus.UNKNOWN
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


class Runner:
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
        # Please note that this limit is rounded to the nearest power of 2, larger than the limit
        if not hasattr(fcntl, "F_SETPIPE_SZ"):
            fcntl.F_SETPIPE_SZ = 1031
        fcntl.fcntl(pipe[1], fcntl.F_SETPIPE_SZ, max_size)
        # Change the blocking policy to not block but fail whenever the output limit is exceeded
        fcntl.fcntl(pipe[1], fcntl.F_SETFL, os.O_NONBLOCK)
        return pipe

    @staticmethod
    def run(sandbox: Sandbox, command, input_bytes=None, privileged=False) -> (bytes, bytes):
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
        command = COMMAND_WRAPPER.format(command=command, timeout=timeout)

        # Instead of ignoring it, merge the stderr to stdout if requested
        if print_stderr:
            command = command.replace("/dev/null", "&1")

        # Execute the wrapped command and parse the execution info from /usr/bin/time's output
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=sandbox, command=command, input_bytes=input_bytes, privileged=privileged
        )
        info = stderr_bytes.decode(encoding=config.OUTPUT_ENCODING).strip()
        exit_code, exec_time, exec_memory = parse_exec_info(info=info, timeout=timeout, logger=logger)
        return RunResult(exit_code=exit_code, exec_time=exec_time, exec_memory=exec_memory, output=stdout_bytes)

    @staticmethod
    def run_program(sandbox: Sandbox, executable_path, memory_limit, timeout,
                    input_bytes=None, print_stderr=False, args=None, privileged=False) -> RunResult:
        # Copy the executable to the sandbox directory
        sandbox.put_file(executable_path)

        # Generate the command which runs the executable (it is language-specific)
        executable_name = os.path.basename(executable_path)
        executable_language = common.get_language_by_exec_name(executable_name)
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
        run_result.exec_time = max(0, run_result.exec_time - common.get_time_offset(executable_language))
        run_result.exec_memory = max(0, run_result.exec_memory - common.get_memory_offset(executable_language))

        # The caller function should populate the rest of the fields of the RunResult
        return run_result
