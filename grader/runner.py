"""
Runs the executable in a sandbox and returns the result for a single test case
"""

import logging
import subprocess
from os import name
from time import sleep, clock
import psutil

import config
from status import TestStatus


class RunResult:
    def __init__(self, status, error_message, exit_code, exec_time, exec_memory, score):
        self.status = status
        self.error_message = error_message
        self.exit_code = exit_code
        self.exec_time = exec_time
        self.exec_memory = exec_memory
        self.score = score


class Runner:
    CHECK_INTERVAL = 0.01
    KILLED_TIME_LIMIT = 1
    KILLED_MEMORY_LIMIT = 2
    KILLED_RUNTIME_ERROR = 3

    def __init__(self, evaluator):
        self.evaluator = evaluator

        # Configure logger
        self.logger = logging.getLogger("Runner")
        self.logger.setLevel(logging.INFO)
        formatter = logging.Formatter(
            "%(levelname)s %(asctime)s (submission {}): %(message)s".format(self.evaluator.id), "%Y-%m-%dT%H:%M:%S")
        self.handler = logging.StreamHandler()
        self.handler.setLevel(logging.INFO)
        self.handler.setFormatter(formatter)
        self.logger.addHandler(self.handler)
        self.logger.propagate = False

    def __del__(self):
        # Remove log handler
        self.logger.removeHandler(self.handler)

    def run(self, test):
        self.logger.info("Running solution on test {}".format(test["inpFile"]))
        inp_file = config.PATH_TESTS + test["inpHash"]
        out_file = self.evaluator.path_sandbox + "output.out"
        sol_file = config.PATH_TESTS + test["solHash"]

        executable = self.evaluator.path_executable
        # Change slashes with backslashes for Windows paths (grr)
        if name == "nt":
            executable = executable.replace("/", "\\")

        result = self.exec_solution(executable, inp_file, out_file)

        if result.error_message != "":
            self.logger.info("Got error while executing test {}: \"{}\"".format(test["inpFile"], result.error_message))
            result.status = TestStatus.RUNTIME_ERROR
            return result

        if result.exec_time > self.evaluator.time_limit:
            result.status = TestStatus.TIME_LIMIT
            return result

        if result.exec_memory > self.evaluator.memory_limit:
            result.status = TestStatus.MEMORY_LIMIT
            return result

        if result.exit_code != 0:
            result.status = TestStatus.RUNTIME_ERROR
            return result

        result.error_message, result.score = Runner.validate_output(out_file, sol_file)
        if result.error_message != "":
            result.status = TestStatus.WRONG_ANSWER
            return result

        result.status = TestStatus.ACCEPTED
        return result

    # TODO:
    # Limit network usage
    # Limit spawning of threads / sub-processes
    # Limit writing to the disk
    # Use checker if provided

    def exec_solution(self, executable, inp_file, out_file):
        inp_data = open(inp_file, "rt")
        out_data = open(out_file, "wt")
        process = psutil.Popen([], executable=executable, stdin=inp_data, stdout=out_data, stderr=subprocess.PIPE)

        exec_time = 0.0
        exec_memory = 0.0
        exit_code = None

        start_time = clock()
        while True:
            sleep(Runner.CHECK_INTERVAL)

            # Process already terminated
            if process.poll() is not None:
                break
            if not process.is_running():
                break
            if clock() - start_time > self.evaluator.time_limit * 2:
                exit_code = Runner.KILLED_RUNTIME_ERROR
                process.kill()
                break

            try:
                # Update statistics
                exec_time = max(exec_time, process.cpu_times().user)
                exec_memory = max(exec_memory, process.memory_full_info().uss / 1048576.0)

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

        self.logger.info("  >> Elapsed time: {0:.3f}s. "
                         "Used memory: {1:.3f}MB. "
                         "Total testing time: {2:.3f}s.".format(exec_time, exec_memory, clock() - start_time))

        exit_code = process.poll() if exit_code is None else exit_code

        error_message = process.communicate()[1]
        error_message = error_message.decode("utf-8") if error_message is not None else ""

        # Leave the parent function decide what the test status will be
        return RunResult(TestStatus.TESTING, error_message, exit_code, exec_time, exec_memory, 0.0)

    @staticmethod
    def validate_output(out_file, sol_file):
        with open(out_file) as out:
            with open(sol_file) as sol:
                while True:
                    out_line = out.readline()
                    sol_line = sol.readline()
                    if not out_line and not sol_line:
                        return "", 1.0

                    out_line = out_line.strip() if out_line else ""
                    sol_line = sol_line.strip() if sol_line else ""
                    if out_line != sol_line:
                        if len(out_line) > 20:
                            out_line = out_line[:20] + "..."
                        if len(sol_line) > 20:
                            sol_line = sol_line[:20] + "..."
                        return "Expected \"{}\" but received \"{}\".".format(out_line, sol_line), 0.0
