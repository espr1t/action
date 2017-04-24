"""
Runs the executable in a sandbox and returns the result for a single test case
"""

import logging
from subprocess import PIPE
from os import getcwd, path
from sys import platform
from tempfile import TemporaryFile
from time import sleep, perf_counter
import psutil
from math import fabs
import config
from status import TestStatus
from queue import Queue
from threading import Thread
from string import printable

if not platform.startswith("win32"):
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

    @staticmethod
    def enqueue_output(out, queue):
        for line in out:
            queue.put(line)
        out.close()

    @staticmethod
    def get_output(queue):
        output = ""
        while not queue.empty() or output == "":
            while not queue.empty():
                output += queue.get()
            sleep(0.01)
        output.replace("\r", "")
        return output

    def play(self, run_id, test, tester, player_one_id, player_one_name, player_one_executable,
                                         player_two_id, player_two_name, player_two_executable):
        # Prepare the run input and output data
        self.logger.info("[Submission {}]       ++ test {}: {} vs {}...".format(
                self.evaluator.id, test['position'], player_one_name, player_two_name))

        inp_file_name, _, _, sandbox, _ = self.prepare_run(test)

        # Read input data
        with open(inp_file_name, "rt") as input_file:
            input_content = input_file.read()

        # Start the tester's process
        tester_executable = getcwd() + "/" + config.PATH_TESTERS + tester + ".o"
        process = psutil.Popen(args=[], cwd=sandbox, executable=tester_executable,
                               stdin=PIPE, stdout=PIPE, stderr=PIPE, universal_newlines=True)

        # Listen to tester's output asynchronously
        tester_queue = Queue()
        Thread(target=Runner.enqueue_output, args=[process.stdout, tester_queue], daemon=True).start()

        # Write the input file to the tester
        process.stdin.write(input_content)
        process.stdin.flush()

        player_one_score = 0
        player_two_score = 0
        player_one_exec_time = 0
        player_one_exec_memory = 0
        player_two_exec_time = 0
        player_two_exec_memory = 0
        message = "The game has not yet completed."

        # Start the game loop
        cur_move = 0
        killed = False
        start_time = perf_counter()

        while True:
            sleep(0.01)
            # Process already terminated
            if process.poll() is not None:
                break
            if not process.is_running():
                break

            # The game is taking too long
            if perf_counter() - start_time > config.MAX_GAME_LENGTH:
                message = "Game execution exceeded limit of {} seconds.".format(config.MAX_GAME_LENGTH)
                process.kill()
                killed = True
                break

            cur_move += 1

            # Prepare AI's environment
            ai_input_text = Runner.get_output(tester_queue)

            ai_input = TemporaryFile()
            ai_input.write(ai_input_text.encode())
            ai_input.seek(0)
            ai_output = TemporaryFile()

            # Run the AI (alternating players every turn)
            player = player_one_name if cur_move % 2 == 1 else player_two_name
            executable = player_one_executable if cur_move % 2 == 1 else player_two_executable
            result = self.exec_solution(sandbox, executable, ai_input, ai_output)
            if cur_move % 2 == 1:
                player_one_exec_time = max(player_one_exec_time, result.exec_time)
                player_one_exec_memory = max(player_one_exec_memory, result.exec_memory)
            else:
                player_two_exec_time = max(player_two_exec_time, result.exec_time)
                player_two_exec_memory = max(player_two_exec_memory, result.exec_memory)

            # Pass the output to the tester
            ai_output.flush()
            ai_output.seek(0)

            # Check if the output contains at least one printable character
            ai_output_text = ai_output.read().decode().replace("\r", "")
            ai_output_empty = not any(ch in printable for ch in ai_output_text)

            # RE, TL, ML, or no output - stop the game and declare the other player as a winner
            if result.exit_code != 0 or result.exec_time > self.evaluator.time_limit \
                    or result.exec_memory > self.evaluator.memory_limit or ai_output_empty:
                if result.exec_time > self.evaluator.time_limit:
                    message = "{}'s solution used more than the allowed {:.2f} seconds.".format(
                            player, self.evaluator.time_limit)
                elif result.exec_memory > self.evaluator.memory_limit:
                    message = "{}'s solution used more than the allowed {:.0f} megabytes.".format(
                            player, self.evaluator.memory_limit / 1048576)
                elif result.exit_code != 0:
                    message = "{}'s solution crashed.".format(player)
                    self.logger.info("Exit code: {}".format(result.exit_code))
                elif ai_output_empty:
                    message = "{}'s solution did not print any output.".format(player)

                player_one_score = 0.0 if player == player_one_name else 1.0
                player_two_score = 0.0 if player == player_two_name else 1.0

                process.kill()
                killed = True
                break

            # Print only the first line of output and ensure there is a new line at the end
            process.stdin.write(ai_output_text.splitlines()[0] + "\n")
            process.stdin.flush()

            ai_input.close()
            ai_output.close()

        """
        If the game ended properly the tester should have printed the results in the format:
            >> line 1: score player one
            >> line 2: score player two
            >> line 3: message
        """
        if not killed:
            final_message = Runner.get_output(tester_queue)
            player_one_score = float(final_message.split("\n")[0])
            player_two_score = float(final_message.split("\n")[1])
            message = final_message.split("\n")[2]
            message = message.replace("First player", player_one_name)
            message = message.replace("first player", player_one_name)
            message = message.replace("Second player", player_two_name)
            message = message.replace("second player", player_two_name)

        match_log = ""
        for line in process.stderr:
            match_log += line

        # Update the frontend
        results = [{
            "id": run_id,
            "position": test["position"],
            "status": TestStatus.ACCEPTED.name,
            "message": message,
            "player_one_id": player_one_id,
            "player_one_score": player_one_score,
            "player_one_exec_time": player_one_exec_time,
            "player_one_exec_memory": round(player_one_exec_memory / 1048576.0, 2),  # Convert back to megabytes
            "player_two_id": player_two_id,
            "player_two_score": player_two_score,
            "player_two_exec_time": player_two_exec_time,
            "player_two_exec_memory": round(player_two_exec_memory / 1048576.0, 2),  # Convert back to megabytes
            "match_log": match_log
        }]
        self.evaluator.update_frontend("", results)
        return

    def run(self, run_id, test):
        # Update the frontend that we are running this test
        results = [{
            "id": run_id,
            "position": test["position"],
            "status": TestStatus.TESTING.name,
            "score": 0
        }]
        self.evaluator.update_frontend("", results)

        start_time = perf_counter()

        # Prepare the run input and output data
        inp_file_path, out_file_path, sol_file_path, sandbox, executable = self.prepare_run(test)

        # Open the input and output files
        inp_file = open(inp_file_path, "rt")
        out_file = open(out_file_path, "wt")

        # Execute the solution
        result = self.exec_solution(sandbox, executable, inp_file, out_file)

        # Close the input and output files
        inp_file.close()
        out_file.close()

        # Determine the proper execution status (OK, WA, TL, ML, RE) and score for this test
        result.status, result.error_message, result.score =\
            self.determine_status(test, result, inp_file_path, out_file_path, sol_file_path)

        total_time = perf_counter() - start_time
        score = " ({})".format(result.score) if 0.0 < result.score < 1.0 else ""
        self.logger.info("[Submission {}]    -- executed {}: Time: {:.3f}s. Memory: {:.2f}MB. Testing time: {:.3f}s :: {}{}".format(
                self.evaluator.id, test["inpFile"], result.exec_time, result.exec_memory / 1048576.0, total_time, result.status.name, score))

        if result.status == TestStatus.WRONG_ANSWER:
            self.logger.info("[Submission {}]      == {}".format(self.evaluator.id, result.error_message))

        # Update the frontend once again that we the testing has been completed (along with TL, ML, and score this time)
        results = [{
            "id": run_id,
            "position": test["position"],
            "status": result.status.name,
            "error_message": result.error_message,
            "exec_time": result.exec_time,
            "exec_memory": round(result.exec_memory / 1048576.0, 2),  # Convert back to megabytes
            "score": result.score
        }]
        self.evaluator.update_frontend("", results)
        return result

    """
    Prepare the run by configuring the correct input and output data for this test,
    as well as the sandbox and executable paths.
    """
    def prepare_run(self, test):
        inp_file = config.PATH_TESTS + test["inpHash"]
        out_file = self.evaluator.path_sandbox + test["inpFile"].replace(".in", ".out")
        sol_file = config.PATH_TESTS + test["solHash"]
        sandbox = getcwd() + "/" + self.evaluator.path_sandbox
        executable = getcwd() + "/" + self.evaluator.path_executable

        # Change slashes with backslashes for Windows paths (grr)
        if platform.startswith("win32"):
            sandbox = sandbox.replace("/", "\\")
            executable = executable.replace("/", "\\")

        return inp_file, out_file, sol_file, sandbox, executable

    """
    Determines the proper execution status (OK, WA, TL, ML, RE) and score of the solution
    """
    def determine_status(self, test, result, inp_file, out_file, sol_file):
        if result.error_message != "":
            self.logger.info("[Submission {}] Got error while executing test {}: \"{}\"".format(
                self.evaluator.id, test["inpFile"], result.error_message))
            return TestStatus.RUNTIME_ERROR, result.error_message, 0
        elif result.exec_time > self.evaluator.time_limit:
            return TestStatus.TIME_LIMIT, "", 0
        elif result.exec_memory > self.evaluator.memory_limit:
            return TestStatus.MEMORY_LIMIT, "", 0
        elif result.exit_code != 0:
            return TestStatus.RUNTIME_ERROR, "", 0
        else:
            error_message, score = self.validate_output(inp_file, out_file, sol_file)
            if error_message != "":
                return TestStatus.WRONG_ANSWER, error_message, 0
            else:
                return TestStatus.ACCEPTED, "", score

    # TODO: Limit network usage

    # Use the resource library to limit process resources when on UNIX
    if not platform.startswith("win32"):
        @staticmethod
        def set_restrictions(time_limit, java):
            # Set the user to a low-privileged one, if we have the privileges for this
            try:
                setuid(1001)
            except OSError:
                pass

            # Kill the solution if it exceeds twice the time limit of the problem (wall-clock)
            resource.setrlimit(resource.RLIMIT_CPU, (time_limit * 2, time_limit * 2))

            # Kill the solution if it exceeds 1GB of memory
            if not java:
                resource.setrlimit(resource.RLIMIT_AS, (1073741824, 1073741824))

            # Set the stack to be 64MB
            resource.setrlimit(resource.RLIMIT_STACK, (67108864, 67108864))

            # Kill the solution if it writes more than 16MB of output to stdout/stderr
            resource.setrlimit(resource.RLIMIT_FSIZE, (16777216, 16777216))

            # Although setting a limit on the file handles doesn't quite work, it seems the programs cannot write
            # to files anyway. We create the sandbox dir with the user running the grader (typically root),
            # and since they are owned by a more privileged user, the program cannot write in it. It can, however,
            # write in its own home directory, so we should chroot the process.
            if not java:
                resource.setrlimit(resource.RLIMIT_NOFILE, (4, 4))

            # Deny creation of new processes and threads
            if not java:
                resource.setrlimit(resource.RLIMIT_NPROC, (1, 1))
            else:
                resource.setrlimit(resource.RLIMIT_NPROC, (999, 999))

            # Deny creation of core dump files
            resource.setrlimit(resource.RLIMIT_CORE, (0, 0))

            # Deny writing to message queues
            resource.setrlimit(resource.RLIMIT_MSGQUEUE, (0, 0))

    def exec_solution(self, sandbox, executable, inp_file, out_file):
        exec_time = 0.0
        exec_memory = 0.0
        exit_code = None

        children_limit = 0
        thread_limit = config.THREAD_LIMIT_CPP
        time_offset = config.TIME_OFFSET_CPP
        memory_offset = config.MEMORY_OFFSET_CPP

        language = "C++" if executable.endswith(".o") else "Java"

        if language == "Java":
            thread_limit = config.THREAD_LIMIT_JAVA
            time_offset = config.TIME_OFFSET_JAVA
            memory_offset = config.MEMORY_OFFSET_JAVA

        execution_time_limit = max(1.0, self.evaluator.time_limit * 2)

        # Calling the executable doesn't work on Windows 10 Ubuntu Bash if we don't provide the full path
        executable = path.join(getcwd(), executable)

        args = []
        if language == "Java":
            xms = "-Xms{}k".format(1024)
            xmx = "-Xmx{}k".format(self.evaluator.memory_limit // 1024)
            args = ["java", "-XX:-UseSerialGC", xms, xmx, "-jar", executable]
            executable = None

        start_time = perf_counter()
        if platform.startswith("win32"):
            process = psutil.Popen(args=args, executable=executable, cwd=sandbox, stdin=inp_file, stdout=out_file)
        else:
            process = psutil.Popen(args=args, executable=executable, cwd=sandbox, stdin=inp_file, stdout=out_file,
                                   preexec_fn=(lambda: Runner.set_restrictions(
                                       self.evaluator.time_limit, language == "Java")))

        check_interval = config.EXECUTION_MIN_CHECK_INTERVAL

        while True:
            sleep(check_interval)
            # Exponentially increase the check interval, until a certain max time gap
            # This works fine for very short-lived programs as well as long-lived ones
            check_interval = min(config.EXECUTION_MAX_CHECK_INTERVAL, check_interval * 2)

            # Process already terminated
            if process.poll() is not None:
                break
            if not process.is_running():
                break

            # Process has hung up
            if perf_counter() - start_time > execution_time_limit:
                exit_code = Runner.KILLED_RUNTIME_ERROR
                process.kill()
                break

            try:
                # Update statistics
                exec_time = max(0, max(exec_time, process.cpu_times().user) - time_offset)
                # RSS should be available on both Windows and Unix
                exec_memory = max(0, max(exec_memory, process.memory_info().rss) - memory_offset)

                """
                # Consider using USS instead of PSS if available (currently it returns zeroes only)
                mem_info = process.memory_full_info()
                # Need to check with "in" for named tuple
                exec_memory = max(exec_memory, mem_info.pss if "pss" in mem_info else mem_info.rss)
                """

                # Spawning processes or threads
                #print("Running for {} seconds ({} children and {} threads)...".format(
                #    exec_time, len(process.children()), process.num_threads()))
                if len(process.children()) > children_limit or process.num_threads() > thread_limit:
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

        if exit_code is None:
            exit_code = process.wait(timeout=0.5)
            if exit_code is None:
                exit_code = Runner.KILLED_RUNTIME_ERROR

        error_message = ""

        # Leave the parent function decide what the test status will be
        return RunResult(TestStatus.TESTING, error_message, exit_code, exec_time, exec_memory, 0.0)

    # TODO: Take this to a separate file (validator.py)
    def validate_output(self, inp_file, out_file, sol_file):
        if self.evaluator.checker is None:
            return self.validate_output_directly(out_file, sol_file)
        else:
            return self.validate_output_with_checker(inp_file, out_file, sol_file)

    def validate_output_directly(self, out_file, sol_file):
        with open(out_file, "rt", encoding="cp866") as out:
            with open(sol_file, "rt", encoding="cp866") as sol:
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
                            if out_tokens[i] == sol_tokens[i]:
                                continue
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
                                self.logger.info("[Submission {}] Double parsing failed!".format(self.evaluator.id))
                                relative_comparison_okay = False
                                break

                    if relative_comparison_okay:
                        continue

                    # If none of the checks proved the answer to be correct, return a Wrong Answer
                    if len(out_line) > 20:
                        out_line = out_line[:17] + "..."
                    if len(sol_line) > 20:
                        sol_line = sol_line[:17] + "..."
                    return "Expected \"{}\" but received \"{}\".".format(sol_line, out_line), 0.0

    def validate_output_with_checker(self, inp_file, out_file, sol_file):
        checker_binary_path = config.PATH_CHECKERS + self.evaluator.checker
        process = psutil.Popen(args=[checker_binary_path, inp_file, out_file, sol_file],
                               executable=checker_binary_path, cwd=getcwd(), stdout=PIPE, stderr=PIPE)
        try:
            exit_code = process.wait(timeout=config.CHECKER_TIMEOUT)
        except psutil.TimeoutExpired:
            self.logger.error("Internal Error: Checker took more than the allowed {}s.".format(config.CHECKER_TIMEOUT))
            process.terminate()
            return "Checker Timeout", 0.0

        if exit_code != 0:
            return "Checker returned non-zero exit code: {}".format(exit_code), 0.0

        result = process.communicate()[0]
        result = result.decode("utf-8") if result is not None else "0.0"
        lines = result.splitlines()

        score = 0.0
        message = ""
        if len(lines) > 0:
            score = float(lines[0])
        if len(lines) > 1:
            message = lines[1] if lines[1] != "OK" else ""
        return message, score
