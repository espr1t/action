"""
Runs the executable in a sandbox and returns the result for a single test case
"""

import logging
from subprocess import PIPE
from os import name, getcwd
from time import sleep, perf_counter
import psutil
from math import fabs

import config
from status import TestStatus

if name != "nt":
    import resource
    from os import setuid


class RunResult:
    def __init__(self, status, error_message, exit_code, exec_time, exec_memory, score):
        self.status = status
        self.error_message = error_message
        self.exit_code = exit_code
        self.exec_time = exec_time
        self.exec_memory = exec_memory
        self.score = score


class Runner:
    KILLED_TIME_LIMIT = 1
    KILLED_MEMORY_LIMIT = 2
    KILLED_RUNTIME_ERROR = 3

    def __init__(self, evaluator):
        self.evaluator = evaluator

        # Configure logger
        self.logger = logging.getLogger("runnr")

    def __del__(self):
        # Remove log handler
        self.logger.removeHandler(self.handler)

    def run(self, test):
        inp_file = config.PATH_TESTS + test["inpHash"]
        out_file = self.evaluator.path_sandbox + test["inpFile"].replace(".in", ".out")
        sol_file = config.PATH_TESTS + test["solHash"]
        sandbox = getcwd() + "/" + self.evaluator.path_sandbox
        executable = getcwd() + "/" + self.evaluator.path_executable

        # Change slashes with backslashes for Windows paths (grr)
        if name == "nt":
            sandbox = sandbox.replace("/", "\\")
            executable = executable.replace("/", "\\")

        start_time = perf_counter()
        result = self.exec_solution(sandbox, executable, inp_file, out_file)

        if result.error_message != "":
            self.logger.info("[Submission {}] Got error while executing test {}: \"{}\"".format(
                             self.evaluator.id, test["inpFile"], result.error_message))
            result.status = TestStatus.RUNTIME_ERROR
        elif result.exec_time > self.evaluator.time_limit:
            result.status = TestStatus.TIME_LIMIT
        elif result.exec_memory > self.evaluator.memory_limit:
            result.status = TestStatus.MEMORY_LIMIT
        elif result.exit_code != 0:
            result.status = TestStatus.RUNTIME_ERROR
        else:
            result.error_message, result.score = Runner.validate_output(out_file, sol_file)
            if result.error_message != "":
                result.status = TestStatus.WRONG_ANSWER
            else:
                result.status = TestStatus.ACCEPTED

        total_time = perf_counter() - start_time
        self.logger.info("[Submission {}]    -- executed {}: Time: {:.3f}s. Memory: {:.2f}MB. Testing time: {:.3f}s :: {}".format(
                self.evaluator.id, test["inpFile"], result.exec_time, result.exec_memory / 1048576.0, total_time, result.status.name))

        return result

    # TODO:
    # Limit network usage
    # Use checker if provided

    # Use the resource library to limit process resources when on UNIX
    if name != "nt":
        @staticmethod
        def set_restrictions(time_limit):
            # Set the user to a low-privileged one, if we have the privileges for this
            try:
                setuid(1001)
            except OSError:
                pass

            # Set the maximum execution time of the process to the Time Limit of the problem
            resource.setrlimit(resource.RLIMIT_CPU, (time_limit, time_limit))

            # Set the maximum address space to 1GB (so the process cannot consume all memory before being killed)
            resource.setrlimit(resource.RLIMIT_AS, (1073741824, 1073741824))

            # Set the stack to be 8MB
            resource.setrlimit(resource.RLIMIT_STACK, (8388608, 8388608))

            # Set the maximum output to stdout/stderr be 16MB
            resource.setrlimit(resource.RLIMIT_FSIZE, (16777216, 16777216))

            # Although setting a limit on the file handles doesn't quite work, it seems the programs cannot write
            # to files anyway. We create the sandbox dir with the user running the grader (typically root),
            # and since they are owned by a more privileged user, the program cannot write in it. It can, however,
            # write in its own home directory, so we should chroot the process.
            resource.setrlimit(resource.RLIMIT_NOFILE, (4, 4))

            # Deny creation of new processes and threads
            resource.setrlimit(resource.RLIMIT_NPROC, (1, 1))

            # Deny creation of core dump files
            resource.setrlimit(resource.RLIMIT_CORE, (0, 0))

            # Deny writing to message queues
            resource.setrlimit(resource.RLIMIT_MSGQUEUE, (0, 0))

    def exec_solution(self, sandbox, executable, inp_file, out_file):
        exec_time = 0.0
        exec_memory = 0.0
        exit_code = None

        inp_file_handle = open(inp_file, "rt")
        out_file_handle = open(out_file, "wt")

        start_time = perf_counter()
        if name == "nt":
            process = psutil.Popen(args=[], executable=executable, cwd=sandbox,
                                   stdin=inp_file_handle, stdout=out_file_handle, stderr=PIPE)
        else:
            process = psutil.Popen(args=[], executable=executable, cwd=sandbox,
                                   stdin=inp_file_handle, stdout=out_file_handle, stderr=PIPE,
                                   preexec_fn=(lambda: Runner.set_restrictions(self.evaluator.time_limit)))

        while True:
            sleep(config.EXECUTION_CHECK_INTERVAL)

            # Process already terminated
            if process.poll() is not None:
                break
            if not process.is_running():
                break

            # Process has hung up
            if perf_counter() - start_time > self.evaluator.time_limit * 2:
                exit_code = Runner.KILLED_RUNTIME_ERROR
                process.kill()
                break

            try:
                # Update statistics
                exec_time = max(exec_time, process.cpu_times().user)
                # Consider using USS instead of PSS if available (currently it returns zeroes only)
                mem_info = process.memory_full_info()
                exec_memory = max(exec_memory, mem_info.pss if "pss" in mem_info else mem_info.rss)

                # Spawning processes or threads
                if process.num_threads() > 2:
                    exit_code = Runner.KILLED_RUNTIME_ERROR
                    process.kill()
                    break

                # Time Limit, kill the process
                if exec_time > self.evaluator.time_limit:
                    exit_code = Runner.KILLED_TIME_LIMIT
                    process.kill()
                    break

                # Memory Limit, kill the process
                if exec_memory > self.evaluator.memory_limit:
                    exit_code = Runner.KILLED_MEMORY_LIMIT
                    process.kill()
                    break

            except psutil.NoSuchProcess:
                break

        exit_code = process.poll() if exit_code is None else exit_code

        #error_message = process.communicate()[1]
        #error_message = error_message.decode("utf-8") if error_message is not None else ""
        error_message = ""

        # Close the files
        inp_file_handle.close()
        out_file_handle.close()

        # Leave the parent function decide what the test status will be
        return RunResult(TestStatus.TESTING, error_message, exit_code, exec_time, exec_memory, 0.0)

    @staticmethod
    def validate_output(out_file, sol_file):
        with open(out_file, "rt") as out:
            with open(sol_file, "rt") as sol:
                while True:
                    out_line = out.readline()
                    sol_line = sol.readline()
                    if not out_line and not sol_line:
                        return "", 1.0

                    out_line = out_line.strip() if out_line else ""
                    sol_line = sol_line.strip() if sol_line else ""

                    if out_line == sol_line:
                        continue

                    # If a float (or a list of floats), try comparing with absolute or relative error
                    out_tokens = out_line.split()
                    sol_tokens = sol_line.split()
                    relative_comparison_okay = True
                    if len(out_tokens) != len(sol_tokens):
                        relative_comparison_okay = False
                    else:
                        for i in range(len(out_tokens)):
                            try:
                                out_num = float(out_tokens[i])
                                sol_num = float(sol_tokens[i])
                                if fabs(out_num - sol_num) > config.FLOAT_PRECISION:
                                    abs_out_num, abs_sol_num = fabs(out_num), fabs(sol_num)
                                    if abs_out_num < (1.0 - config.FLOAT_PRECISION) * abs_sol_num or \
                                            abs_out_num > (1.0 + config.FLOAT_PRECISION) * abs_sol_num:
                                        relative_comparison_okay = False
                                        break
                            except ValueError:
                                print("Parsing failed!")
                                relative_comparison_okay = False
                                break

                    if relative_comparison_okay:
                        continue

                    # If none of the checks proved the answer to be correct, return a Wrong Answer
                    if len(out_line) > 20:
                        out_line = out_line[:20] + "..."
                    if len(sol_line) > 20:
                        sol_line = sol_line[:20] + "..."
                    return "Expected \"{}\" but received \"{}\".".format(sol_line, out_line), 0.0
