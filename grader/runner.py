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


class Runner:
    CHECK_INTERVAL = 0.01
    KILLED_TIME_LIMIT = 1
    KILLED_MEMORY_LIMIT = 2
    KILLED_RUNTIME_ERROR = 3

    @staticmethod
    def run(evaluator, test):
        logging.info("Executing solution {} on test {}".format(evaluator.id, evaluator.tests[test]["inpFile"]))
        inp_file = config.PATH_TESTS + evaluator.tests[test]["inpHash"]
        out_file = evaluator.path_sandbox + "output.out"
        sol_file = config.PATH_TESTS + evaluator.tests[test]["solHash"]

        executable = evaluator.path_executable
        # Change slashes with backslashes for Windows paths (grr)
        if name == "nt":
            executable = executable.replace("/", "\\")

        exec_time, exec_memory, exit_code, error = Runner.exec_solution(
                executable, inp_file, out_file, evaluator.time_limit, evaluator.memory_limit)

        if error != "":
            logging.info("Got error while executing test {} of submission {}: \"{}\"".format(
                evaluator.tests[test]["inpFile"], evaluator.id, error))
            return TestStatus.RUNTIME_ERROR, error

        if exec_time > evaluator.time_limit:
            return TestStatus.TIME_LIMIT, "Execution time: {} seconds.".format(exec_time)

        if exec_memory > evaluator.memory_limit:
            return TestStatus.MEMORY_LIMIT, "Peak memory: {} megabytes.".format(exec_memory)

        if exit_code != 0:
            logging.info("Process exited with non-zero exit code while executing test {} of submission {}: \"{}\"".
                         format(evaluator.tests[test]["inpFile"], evaluator.id, exit_code))
            return TestStatus.RUNTIME_ERROR, "Exit code: {}".format(exit_code)

        message = Runner.validate_output(out_file, sol_file)
        if message != "":
            return TestStatus.WRONG_ANSWER, message

        return TestStatus.ACCEPTED, ""

    # TODO:
    # Limit network usage
    # Limit spawning of threads / sub-processes
    # Limit writing to the disk
    # Use checker if provided

    @staticmethod
    def exec_solution(executable, inp_file, out_file, time_limit, memory_limit):
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
            if clock() - start_time > time_limit * 2:
                exit_code = Runner.KILLED_RUNTIME_ERROR
                process.kill()
                break

            try:
                # Update statistics
                exec_time = max(exec_time, process.cpu_times().user)
                exec_memory = max(exec_memory, process.memory_full_info().uss / 1048576.0)

                """
                logging.warning(str(process.cpu_times()))
                logging.warning(str(process.memory_full_info()))
                logging.warning("Current execution time: {0:.2f} seconds".format(exec_time))
                logging.warning("Current execution memory: {0:.2f} megabytes".format(exec_memory))
                """

                # Spawning processes or threads
                if process.num_threads() > 2:
                    exit_code = Runner.KILLED_RUNTIME_ERROR
                    process.kill()
                    break

                # Time Limit, kill the process
                if exec_time > time_limit:
                    exit_code = Runner.KILLED_TIME_LIMIT
                    process.kill()
                    break

                # Memory Limit, kill the process
                if exec_memory > memory_limit:
                    exit_code = Runner.KILLED_MEMORY_LIMIT
                    process.kill()
                    break

            except psutil.NoSuchProcess:
                break

        logging.info("Elapsed time: {0:.3f} seconds. "
                     "Used memory: {1:.3f} megabytes. "
                     "Total testing time: {2:.3f} seconds.".format(exec_time, exec_memory, clock() - start_time))

        exit_code = process.poll() if exit_code is None else exit_code

        error = process.communicate()[1]
        error = error.decode("utf-8") if error is not None else ""

        return exec_time, exec_memory, exit_code, error

    @staticmethod
    def validate_output(out_file, sol_file):
        with open(out_file) as out:
            with open(sol_file) as sol:
                while True:
                    out_line = out.readline()
                    sol_line = sol.readline()
                    if not out_line and not sol_line:
                        return ""

                    out_line = out_line.strip() if out_line else ""
                    sol_line = sol_line.strip() if sol_line else ""
                    if out_line != sol_line:
                        if len(out_line) > 20:
                            out_line = out_line[:20] + "..."
                        if len(sol_line) > 20:
                            sol_line = sol_line[:20] + "..."
                        return "Expected \"{}\" but received \"{}\".".format(out_line, sol_line)
