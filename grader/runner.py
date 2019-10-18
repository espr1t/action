"""
Runs the executable in a sandbox and returns the result for a single test case
"""

import os
import json
from time import sleep, perf_counter
import subprocess
import config
from queue import Queue
from threading import Thread
from string import printable
from tempfile import NamedTemporaryFile
from signal import SIGKILL, SIGTERM
from hashlib import md5

import common
from status import TestStatus
from executor import Executor
from sandbox import Sandbox
from validator import Validator
from run_info import RunInfo

# Redirect the run_command's stderr to /dev/null, but keep the output from /usr/bin/time (which is also stderr)
# http://man7.org/linux/man-pages/man1/time.1.html
# --format argument '%U' prints "Elapsed CPU seconds in User mode"
# --format argument '%e' prints "Elapsed real (clock) time in seconds" (backup only - volatile and also can be tricked)
# --format argument '%M' prints "Maximum resident set size (kbytes)"
RUN_TEST_COMMAND = "/bin/bash -c \"{time} /bin/bash -c \\\"{timeout} {command}\\\" ; >&2 printf '%d' $?\"".format(
    time="/usr/bin/time --quiet --format='%U %e %M'",
    # Send a SIGTERM signal after {timeout} seconds, but ensure the program is killed after 0.2 more seconds
    timeout="/usr/bin/timeout --preserve-status --kill-after=0.2s --signal=SIGTERM {timeout}s",
    command="{run_command} < input.txt > output.txt 2> /dev/null"
)


logger = common.get_logger(__name__)


class Runner:
    def __init__(self, evaluator):
        self.evaluator = evaluator

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

    def run_game(self, result_id, test, player_one_id, player_one_name, player_one_executable,
                                        player_two_id, player_two_name, player_two_executable):
        # Prepare the run input and output data
        logger.info("Submit {} | Test {}: {} vs {} (result_id = {})...".format(
                self.evaluator.id, test["position"], player_one_name, player_two_name, result_id))

        inp_file_path = os.path.join(config.PATH_TESTS, test["inpHash"])

        # Read input data
        with open(inp_file_path, "rt") as input_file:
            input_content = input_file.read()

        # Start the tester's process
        process = subprocess.Popen(
            args=[],
            executable=self.evaluator.path_tester_executable,
            stdin=subprocess.PIPE,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True)

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

            # The game is taking too long
            if perf_counter() - start_time > config.MAX_GAME_LENGTH:
                message = "Game execution exceeded limit of {} seconds.".format(config.MAX_GAME_LENGTH)
                process.kill()
                killed = True
                break

            cur_move += 1

            # Prepare AI's environment
            ai_input_text = Runner.get_output(tester_queue)

            ai_input = NamedTemporaryFile()
            ai_input.write(ai_input_text.encode())
            ai_input.seek(0)

            # Run the AI (alternating players every turn)
            player = player_one_name if cur_move % 2 == 1 else player_two_name
            executable = player_one_executable if cur_move % 2 == 1 else player_two_executable

            run_info = RunInfo(
                solution_path=executable,
                tester_path=None,
                time_limit=self.evaluator.time_limit,
                memory_limit=self.evaluator.memory_limit
            )

            result = Runner.run_solution(run_info, ai_input.name)
            if cur_move % 2 == 1:
                player_one_exec_time = max(player_one_exec_time, result.exec_time)
                player_one_exec_memory = max(player_one_exec_memory, result.exec_memory)
            else:
                player_two_exec_time = max(player_two_exec_time, result.exec_time)
                player_two_exec_memory = max(player_two_exec_memory, result.exec_memory)

            # Check if the output contains at least one printable character
            ai_output_text = result.output.replace("\r", "")
            ai_output_empty = not any(ch in printable for ch in ai_output_text)

            # RE, TL, ML, or no output - stop the game and declare the other player as a winner
            if result.exit_code != 0 or result.exec_time > self.evaluator.time_limit \
                    or result.exec_memory > self.evaluator.memory_limit or ai_output_empty:
                if result.exec_time > self.evaluator.time_limit:
                    message = "{}'s solution used more than the allowed {:.2f} seconds.".format(
                            player, self.evaluator.time_limit)
                    logger.info("Submit {} | Test {}: {}".format(self.evaluator.id, test["position"], message))
                elif result.exec_memory > self.evaluator.memory_limit:
                    message = "{}'s solution used more than the allowed {:.0f} megabytes.".format(
                            player, self.evaluator.memory_limit / 1048576)
                    logger.info("Submit {} | Test {}: {}".format(self.evaluator.id, test["position"], message))
                elif result.exit_code != 0:
                    message = "{}'s solution crashed.".format(player)
                    logger.info("Submit {} | Test {}: {}".format(self.evaluator.id, test["position"], message))
                elif ai_output_empty:
                    message = "{}'s solution did not print any output.".format(player)
                    logger.info("Submit {} | Test {}: {}".format(self.evaluator.id, test["position"], message))

                player_one_score = 0.0 if player == player_one_name else 1.0
                player_two_score = 0.0 if player == player_two_name else 1.0

                process.kill()
                killed = True
                break

            # Print only the first line of output and ensure there is a new line at the end
            process.stdin.write(ai_output_text.splitlines()[0] + "\n")
            process.stdin.flush()

            ai_input.close()

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
            "id": result_id,
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
        self.evaluator.updater.add_info("", results)
        return

    def run_problem(self, result_id, test):
        # Update the frontend that we are running this test
        results = [{
            "id": result_id,
            "position": test["position"],
            "status": TestStatus.TESTING.name,
            "score": 0
        }]
        self.evaluator.updater.add_info("", results)

        start_time = perf_counter()

        # Prepare the run input and output data
        inp_file_path = os.path.abspath(os.path.join(config.PATH_TESTS, test["inpHash"]))
        sol_file_path = os.path.abspath(os.path.join(config.PATH_TESTS, test["solHash"]))

        run_info = RunInfo(
            time_limit=self.evaluator.time_limit,
            memory_limit=self.evaluator.memory_limit,
            solution_path=self.evaluator.path_executable,
            checker_path=self.evaluator.path_checker_executable,
            compare_floats=self.evaluator.floats
        )

        # Execute the solution
        try:
            result = Runner.run_solution(run_info, inp_file_path)
        except Exception as ex:
            result = common.RunResult(status=TestStatus.INTERNAL_ERROR, error_message=str(ex), exit_code=-1)

        # Create a temporary file and write the output there
        out_file = NamedTemporaryFile(mode="w+t", delete=True)
        out_file_path = os.path.abspath(out_file.name)
        with open(out_file_path, "wb") as out:
            out.write(result.output)
        out_file.seek(0)

        # Determine the proper execution status (OK, WA, TL, ML, RE) and score for this test
        result.status, result.error_message, result.score, result.info = Validator.determine_status(
            submit_id=self.evaluator.id,
            test=test,
            result=result,
            run_info=run_info,
            inp_file=inp_file_path,
            out_file=out_file_path,
            sol_file=sol_file_path
        )

        # Close and delete the temporary output file
        out_file.close()

        total_time = perf_counter() - start_time
        score = " ({})".format(result.score) if 0.0 < result.score < 1.0 else ""
        logger.info("Submit {} | Test {} | Time: {:.2f}s. Memory: {:.2f}MB. Testing: {:.2f}s ({}{})".format(
                self.evaluator.id, test["inpFile"], result.exec_time, result.exec_memory / 1048576.0, total_time, result.status.name, score))

        # if result.status == TestStatus.WRONG_ANSWER:
        #     logger.info("Submit {} | Test {} |   >> {}".format(self.evaluator.id, test["inpFile"], result.error_message))

        # Update the frontend once again that the testing has been completed (along with TL, ML, and score this time)
        results = [{
            "id": result_id,
            "position": test["position"],
            "status": result.status.name,
            "error_message": result.error_message,
            "exec_time": result.exec_time,
            "exec_memory": round(result.exec_memory / 1048576.0, 2),  # Convert back to megabytes
            "score": result.score,
            "info": result.info
        }]
        self.evaluator.updater.add_info("", results)
        return result

    def run_interactive_problem(self, result_id, test):
        # Update the frontend that we are running this test
        results = [{
            "id": result_id,
            "position": test["position"],
            "status": TestStatus.TESTING.name,
            "score": 0
        }]
        self.evaluator.updater.add_info("", results)

        if self.evaluator.path_tester_executable is None:
            logger.error("Interactive problem without a tester!")
            return common.RunResult(status=TestStatus.INTERNAL_ERROR, error_message="Interactive problem without a tester!")

        start_time = perf_counter()

        # Prepare the run input and output data
        inp_file_path = os.path.abspath(os.path.join(config.PATH_TESTS, test["inpHash"]))

        run_info = RunInfo(
            time_limit=self.evaluator.time_limit,
            memory_limit=self.evaluator.memory_limit,
            solution_path=self.evaluator.path_executable,
            tester_path=self.evaluator.path_tester_executable
        )

        # Execute the solution
        try:
            result = Runner.run_interactive_solution(run_info, inp_file_path)
        except Exception as ex:
            result = common.RunResult(status=TestStatus.INTERNAL_ERROR, error_message=str(ex), exit_code=-1)

        # Determine the proper execution status (OK, WA, TL, ML, RE) and score for this test
        result.status, result.error_message, result.score, result.info = Validator.determine_status(
            submit_id=self.evaluator.id,
            test=test,
            result=result,
            run_info=run_info,
            inp_file=inp_file_path,
            out_file=None,
            sol_file=None
        )

        total_time = perf_counter() - start_time
        score = " ({})".format(result.score) if 0.0 < result.score < 1.0 else ""
        logger.info("Submit {} | Test {} | Time: {:.2f}s. Memory: {:.2f}MB. Testing: {:.2f}s ({}{})".format(
                self.evaluator.id, test["inpFile"], result.exec_time, result.exec_memory / 1048576.0, total_time, result.status.name, score))

        # Update the frontend once again that the testing has been completed (along with TL, ML, and score this time)
        results = [{
            "id": result_id,
            "position": test["position"],
            "status": result.status.name,
            "error_message": result.error_message,
            "exec_time": result.exec_time,
            "exec_memory": round(result.exec_memory / 1048576.0, 2),  # Convert back to megabytes
            "score": result.score,
            "info": result.info
        }]
        self.evaluator.updater.add_info("", results)
        return result

    @staticmethod
    def get_run_command(language, executable, memory_limit):
        if language == config.LANGUAGE_CPP:
            return "./{executable}".format(executable=executable)
        if language == config.LANGUAGE_JAVA:
            return "java -Xmx{xmx_in_kb}k -jar {executable}".format(
                xmx_in_kb=memory_limit // 1024, executable=executable
            )
        if language == config.LANGUAGE_PYTHON:
            return "python3 {executable}".format(executable=executable)
        raise Exception("Unsupported language")

    @staticmethod
    def parse_exec_status(run_info, stderr):
        stderr_lines = stderr.strip().splitlines()

        # Fix exit code (it is offset by 128 by timeout command)
        exit_code = int(stderr_lines[-1])
        exit_code = 0 if exit_code == 0 else exit_code - 128

        # Calculate final time and memory (offset for language VM)
        time_memory_info = stderr_lines[-2]
        exec_time = max(0.0, float(time_memory_info.split()[0]) - run_info.time_offset)
        total_time = max(0.0, float(time_memory_info.split()[1]) - run_info.time_offset)
        exec_memory = max(0.0, float(time_memory_info.split()[2]) * 1024 - run_info.memory_offset)

        # If program was killed, use total (clock) time instead
        if exit_code == SIGKILL or exit_code == SIGTERM:
            # If simply being too slow (wasting lots of CPU cycles), show the user time.
            # If blocked (no CPU cycles, but taking a lot of time), show the clock (kill) time.
            if exec_time <= run_info.time_limit:
                exec_time = total_time
        return exit_code, exec_time, exec_memory

    @staticmethod
    def run_solution(run_info, inp_file_path):
        sandbox = Sandbox()

        # Copy the executable and input file to the sandbox directory
        # Also create the output file (by copying an empty file there) so the user can write into it
        empty_file = NamedTemporaryFile(mode="w+t", delete=True)
        empty_file_path = os.path.abspath(empty_file.name)
        if not sandbox.put([
            (run_info.solution_path, run_info.solution_name),
            (inp_file_path, "input.txt"),
            (empty_file_path, "output.txt")
        ]):
            raise Exception("Could not copy executable and input file to container {}.".format(sandbox.container_id))

        # Run the executable, while measuring CPU and Memory consumption
        run_command = Runner.get_run_command(run_info.solution_language, run_info.solution_name, run_info.memory_limit)
        command = RUN_TEST_COMMAND.format(run_command=run_command, timeout=run_info.timeout)
        stdout, stderr = Executor.docker_exec(
            sandbox.container, command, user=sandbox.container_id, workdir="/sandbox/"
        )
        exit_code, exec_time, exec_memory = Runner.parse_exec_status(run_info, stderr)

        # Get the output if everything else seems okay
        output = b""
        if exit_code == 0 and exec_time <= run_info.time_limit and exec_memory <= run_info.memory_limit:
            output = sandbox.get("/sandbox/output.txt")

        # Leave the caller function decide what the test status will be
        return common.RunResult(status=TestStatus.TESTING, exit_code=exit_code, exec_time=exec_time, exec_memory=exec_memory, output=output)

    @staticmethod
    def run_interactive_solution(run_info, inp_file_path):
        sandbox = Sandbox()

        args = {
            "timeout": run_info.timeout,
            "solution_run_command": Runner.get_run_command(run_info.solution_language, run_info.solution_name, run_info.memory_limit),
            "tester_run_command": Runner.get_run_command(run_info.tester_language, run_info.tester_name, run_info.memory_limit)
        }

        # Copy all the needed files to the sandbox directory
        # This includes:
        #   1) the solution (binary)
        #   2) the tester (binary)
        #   3) interaction orchestrator (interactor.py)
        #   4) interaction orchestrator arguments (json file)
        #   5) tester input (text file)
        #   6) solution output / game log (empty file)
        #   7) game result (empty file)

        empty_file = NamedTemporaryFile(mode="w+t", delete=True)
        empty_file_path = os.path.abspath(empty_file.name)

        args_file = NamedTemporaryFile(mode="w+t", delete=True)
        args_file_path = os.path.abspath(args_file.name)
        args_file.write(json.dumps(args, indent=4, sort_keys=True))
        args_file.seek(0)

        if not sandbox.put([
            (run_info.solution_path, run_info.solution_name),
            (run_info.tester_path, run_info.tester_name),
            ("interactor.py", "interactor.py"),
            (args_file_path, "args.txt"),
            (inp_file_path, "input.txt"),
            (empty_file_path, "output.txt"),
            (empty_file_path, "results.txt")
        ]):
            raise Exception("Could not copy interactor files to container {}.".format(sandbox.container_id))

        # Run the executable, while measuring CPU and Memory consumption
        command = RUN_TEST_COMMAND.format(
            run_command="python3 interactor.py",
            timeout=run_info.timeout * 2
        )
        command = command.replace(" < input.txt > output.txt", "")
        stdout, stderr = Executor.docker_exec(
            sandbox.container, command, user=sandbox.container_id, workdir="/sandbox/"
        )
        exit_code, exec_time, exec_memory = Runner.parse_exec_status(run_info, stderr)

        # The actual interactor.py crashed or got killed
        # Don't check memory limit, as it can be caused by child processes (including solution)
        if exec_time > run_info.timeout * 2:
            return common.RunResult(status=TestStatus.INTERNAL_ERROR, error_message="Interactor took too much time to complete.")
        if exit_code != 0:
            return common.RunResult(status=TestStatus.INTERNAL_ERROR, error_message="Interactor exited with non-zero exit code.")

        # Okay, let's assume the interactor was okay. Let's now check if the tester crashed.
        results = sandbox.get("/sandbox/results.txt").decode()
        logger.info("RESULTS:\n{}".format(results))
        results = json.loads(results)
        if results["internal_error"] or results["tester_exit_code"] != 0:
            return common.RunResult(status=TestStatus.INTERNAL_ERROR, error_message="Tester crashed.")

        # Everything with the system seems okay.
        # Replace the exit_code, exec_time, and exec_memory with the ones of the solution.
        stderr = "{} {} {}\n{}".format(
            results["solution_user_time"], results["solution_clock_time"], results["solution_memory"],
            results["solution_exit_code"]
        )
        exit_code, exec_time, exec_memory = Runner.parse_exec_status(run_info, stderr)
        output = "{score}\n{info_message}".format(
            score=(0.0 if "tester_score" not in results else results["tester_score"]),
            info_message=("" if "tester_info_message" not in results else results["tester_info_message"])
        )

        # Get the game log in the replays folder if everything seems normal
        combined_hash = ""
        if exit_code == 0 and exec_time <= run_info.time_limit and exec_memory <= run_info.memory_limit:
            game_log = sandbox.get("/sandbox/output.txt").decode()

            # Record the game log to the /replays folder if not empty
            if game_log != "":
                # Generate a hash of the solution, tester and input
                with open(run_info.solution_path, "rb") as file:
                    combined_hash += md5(file.read()).hexdigest()
                with open(run_info.tester_path, "rb") as file:
                    combined_hash += md5(file.read()).hexdigest()
                with open(inp_file_path, "rb") as file:
                    combined_hash += md5(file.read()).hexdigest()
                combined_hash = md5(combined_hash.encode()).hexdigest()

                # Record the log in the /replays folder using the hash as a name
                game_log_path = os.path.abspath(os.path.join(config.PATH_REPLAYS, combined_hash))
                with open(game_log_path, "wt") as out:
                    out.write(game_log)

        # Leave the caller function decide what the test status will be
        return common.RunResult(status=TestStatus.TESTING, exit_code=exit_code, exec_time=exec_time, exec_memory=exec_memory, output=output, info=combined_hash)
