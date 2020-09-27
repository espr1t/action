"""
Supports execution of a problem on a single test.
Works for problems with direct output comparison, floating-point comparison,
evaluation through a checker and interactive problems (problems, in which the
solution communicates with an author's program, receiving input continuously
(thus, being able to get feedback from the tester - e.g., make guesses/queries).

Execution requires a single Sandbox instance (one worker).
In the case of a problem with a checker, the checker is executed after the solution.
In the case of an interactive problem, the tester and solution are executed in parallel on the same worker.
In the case of a game, the tester and the solutions are executed in sequence on the same worker.
"""

import os
import json
from hashlib import md5
from time import perf_counter
from tempfile import NamedTemporaryFile

import config
import common
from common import TestInfo, TestStatus
from sandbox import Sandbox
from validator import Validator
from runner import Runner, RunConfig, RunResult
from interactor_2P import PLAYER_ONE_NAME, PLAYER_TWO_NAME


logger = common.get_logger(__file__)


def execute_problem(updater, submit_id, result_id, test: TestInfo, run_config: RunConfig):
    # Update the frontend that we are running this test
    updater.add_info(result={
        "id": result_id,
        "position": test.position,
        "status": TestStatus.TESTING.name,
        "score": 0
    })

    start_time = perf_counter()

    # Run the solution and record its exit code, execution time and memory, and output
    try:
        if run_config.tester_path is None:
            run_result = execute_standard(submit_id, test, run_config)
        else:
            run_result = execute_interactive(submit_id, test, run_config)

        # Determine the proper execution status (OK, WA, TL, ML, RE) and score for this test
        if run_result.status != TestStatus.INTERNAL_ERROR:
            validator_result = Validator.determine_status(submit_id, test, run_config, run_result)
            run_result.status, run_result.score, run_result.info, run_result.error =\
                validator_result.status, validator_result.score, validator_result.info, validator_result.error

    except Exception as ex:
        run_result = RunResult(status=TestStatus.INTERNAL_ERROR, error=str(ex))

    total_time = perf_counter() - start_time
    score = " ({})".format(run_result.score) if 0.0 < run_result.score < 1.0 else ""
    logger.info("Submit {id} | Test {test_name} | Time: {time:.2f}s. Memory: {memory:.2f}MB. Testing: {total:.2f}s ({status}{score})".format(
            id=submit_id, test_name=test.inpFile, time=run_result.exec_time, memory=run_result.exec_memory / 1048576.0,
            total=total_time, status=run_result.status.name, score=score))

    # Update the frontend once again that the testing has been completed (along with TL, ML, and score this time)
    updater.add_info(result={
        "id": result_id,
        "position": test.position,
        "status": run_result.status.name,
        "exec_time": round(run_result.exec_time, 2),  # Round to 0.01 seconds
        "exec_memory": round(run_result.exec_memory / 1048576, 2),  # Convert back to megabytes
        "score": run_result.score,
        "info": run_result.info,
        "error_message": run_result.error,
        "replay_id": run_result.replay_id
    })
    return run_result


def execute_standard(submit_id, test: TestInfo, run_config: RunConfig) -> RunResult:
    # Prepare input data (provided to the program through stdin)
    with open(test.inpPath, mode="rb") as inp:
        input_bytes = inp.read()

    # Run the solution inside a sandbox and delete the sandbox to free up the worker
    sandbox = Sandbox()
    run_result = Runner.run_program(
        sandbox=sandbox,
        executable_path=run_config.executable_path,
        memory_limit=run_config.memory_limit,
        timeout=run_config.timeout,
        input_bytes=input_bytes,
        print_stderr=False
    )
    del sandbox

    # If there is a checker, run it as well
    if run_config.checker_path is not None:
        # Create a temporary file and write the output there
        out_file = NamedTemporaryFile(mode="w+b", delete=True)
        with open(out_file.name, "wb") as out:
            out.write(run_result.output)
        out_file.seek(0)

        # Create execution config for the checker and run it
        sandbox = Sandbox()
        sandbox.put_file(test.inpPath, target_name="input.txt")
        sandbox.put_file(out_file.name, target_name="output.txt")
        sandbox.put_file(test.solPath, target_name="solution.txt")
        checker_result = Runner.run_program(
            sandbox=sandbox,
            executable_path=run_config.checker_path,
            memory_limit=config.MAX_EXECUTION_MEMORY,
            timeout=config.CHECKER_TIMEOUT,
            print_stderr=True,
            args=["input.txt", "output.txt", "solution.txt"]
        )
        del sandbox

        # Close and delete temporary file with user's output
        out_file.close()

        if checker_result.exit_code != 0:
            message = "Checker returned non-zero exit code. Checker's output: '{}'".format(checker_result.output)
            logger.error("[Submission {id}] Internal Error: {error}".format(id=submit_id, error=message))
            return RunResult(status=TestStatus.INTERNAL_ERROR, error=message)

        run_result.output = checker_result.output

    return run_result


def save_replay_log(interaction_log):
    log_id = ""
    if interaction_log is not None:
        log_id = md5(interaction_log).hexdigest()
        # Record the log in the /replays folder using the hash as a name
        log_path = os.path.abspath(os.path.join(config.PATH_REPLAYS, log_id))
        with open(log_path, "wb") as out:
            out.write(interaction_log)
    return log_id


def execute_interactive(submit_id, test: TestInfo, run_config: RunConfig) -> RunResult:
    log_file = "interaction.log"
    tester_executable_name = "tester." + run_config.tester_path.split(".")[-1]
    solution_executable_name = "solution." + run_config.executable_path.split(".")[-1]

    # Both the tester and the solution get extra time in order to account for time tester
    # processes input/output. The tester gets additional time to make sure it can print
    # the log and results properly after the solution completes its execution.
    tester_communication_time = min(1.0, run_config.time_limit * 2)
    tester_postprocessing_time = 1.0
    solution_timeout = run_config.timeout + tester_communication_time
    tester_timeout = solution_timeout + tester_postprocessing_time

    args = {
        "tester_timeout": tester_timeout,
        "solution_timeout": solution_timeout,
        "tester_run_command": Runner.get_run_command(
            language=common.get_language_by_exec_name(tester_executable_name),
            executable=tester_executable_name,
            memory_limit=config.MAX_TESTER_MEMORY
        ),
        "solution_run_command": Runner.get_run_command(
            language=common.get_language_by_exec_name(solution_executable_name),
            executable=solution_executable_name,
            memory_limit=run_config.memory_limit
        ),
        "log_file": log_file
    }
    # print(json.dumps(args, indent=4, sort_keys=True))

    empty_file = NamedTemporaryFile(mode="w+t", delete=False)
    empty_file_path = os.path.abspath(empty_file.name)

    # Copy all the needed files to the sandbox directory:
    #   1) wrapper.py
    #   2) the tester executable
    #   3) the solution executable
    #   4) the input file (read by the tester)
    #   5) game log (empty file with write permissions)

    sandbox = Sandbox()
    sandbox.put_file(os.path.join(config.ROOT_DIR, "wrapper.py"))
    sandbox.put_file(run_config.tester_path, target_name=tester_executable_name)
    sandbox.put_file(run_config.executable_path, target_name=solution_executable_name)
    sandbox.put_file(test.inpPath, target_name="input.txt", mode=0o777)
    sandbox.put_file(empty_file_path, target_name=log_file, mode=0o777)

    # Run the executable, while measuring CPU and Memory consumption
    run_result = Runner.run_program(
        sandbox=sandbox,
        executable_path=os.path.join(config.ROOT_DIR, "interactor_1P.py"),
        memory_limit=config.MAX_EXECUTION_MEMORY,
        timeout=config.MAX_GAME_LENGTH,
        input_bytes=json.dumps(args, indent=4, sort_keys=True).encode()
    )
    interaction_log = sandbox.read_file(log_file)
    del sandbox

    # Record the game log to the /replays folder
    replay_id = save_replay_log(interaction_log)

    # The interactor crashed or got killed
    # Don't check memory limit, as it can be caused by child processes (including solution)
    if run_result.exec_time >= config.MAX_GAME_LENGTH:
        message = "Interactor took too much time to complete ({:.3f}s).".format(run_result.exec_time)
        logger.error("Submit {id} | {message}".format(id=submit_id, message=message))
        return RunResult(status=TestStatus.INTERNAL_ERROR, error=message, replay_id=replay_id)
    if run_result.exit_code != 0:
        message = "Interactor exited with non-zero exit code ({}).".format(run_result.exit_code)
        logger.error("Submit {id} | {message}".format(id=submit_id, message=message))
        return RunResult(status=TestStatus.INTERNAL_ERROR, error=message, replay_id=replay_id)

    # Parse the response from the interactor
    try:
        results = json.loads(run_result.output.decode(config.OUTPUT_ENCODING))
    except ValueError:
        message = "Could not decode interactor's output: {}!".format(run_result.output.decode())
        logger.error("Submit {id} | {message}".format(id=submit_id, message=message))
        return RunResult(status=TestStatus.INTERNAL_ERROR, error=message, replay_id=replay_id)

    # print("RESULTS:\n{}".format(json.dumps(results, indent=4, sort_keys=True)))

    # Okay, let's assume the interactor was okay. Now check if the tester crashed.
    if results["internal_error"] or results["tester_exit_code"] != 0:
        message = "Tester crashed or some other internal error happened."
        logger.error("Submit {id} | {message}".format(id=submit_id, message=message))
        return RunResult(status=TestStatus.INTERNAL_ERROR, error=message, replay_id=replay_id)

    # Everything with the system seems okay.
    # Get the score and the solution's exit_code, exec_time, and exec_memory
    exit_code = int(results["solution_exit_code"])
    # Calculate final time and memory (offset for VM start-up time)
    solution_language = common.get_language_by_exec_name(run_config.executable_path)
    exec_time = max(0, float(results["solution_exec_time"]) - common.get_time_offset(solution_language))
    exec_memory = max(0, float(results["solution_exec_memory"]) - common.get_memory_offset(solution_language))
    # Get tester's output
    output = ("" if "tester_message" not in results else results["tester_message"]).encode()

    # Leave the caller function decide what the test status will be
    return RunResult(exit_code=exit_code, exec_time=exec_time, exec_memory=exec_memory, output=output, replay_id=replay_id)


def execute_game(updater, submit_id, result_id, test: TestInfo, run_config: RunConfig,
                 player_one_id, player_one_name, player_one_executable_path,
                 player_two_id, player_two_name, player_two_executable_path) -> RunResult:
    """
    Simultaneously runs a tester program along with several (at least two) users' solutions.
    The users' solution communicate with the tester program and the tester verifies their
    outputs at each move.

    Requires one Sandbox instance (worker): the tester runs on it and executes the solutions on
    the same sandbox one after another anew for each move.
    """
    # Prepare the run input and output data
    logger.info("Submit {} | Test {}: {} vs {} (result_id = {})...".format(
        submit_id, test.position, player_one_name, player_two_name, result_id))

    run_result = execute_two_player_game(submit_id, test, run_config,
                                         player_one_executable_path, player_two_executable_path)

    result = {
            "id": result_id,
            "position": test.position,
            "status": run_result.status.name,
            "message": run_result.error,
            "player_one_id": player_one_id,
            "player_one_score": 0.0,
            "player_one_exec_time": 0.0,
            "player_one_exec_memory": 0.0,
            "player_two_id": player_two_id,
            "player_two_score": 0.0,
            "player_two_exec_time": 0.0,
            "player_two_exec_memory": 0.0,
            "replay_id": run_result.replay_id
    }

    if run_result.status is not TestStatus.INTERNAL_ERROR:
        interactor_response = json.loads(run_result.output.decode())
        result["message"] = interactor_response["message"]
        result["player_one_score"] = interactor_response["player_one_score"]
        result["player_one_exec_time"] = round(interactor_response["player_one_exec_time"], 2)  # Round to 0.01 seconds
        result["player_one_exec_memory"] = round(interactor_response["player_one_exec_memory"] / 1048576, 2)  # Convert back to megabytes
        result["player_two_score"] = interactor_response["player_two_score"]
        result["player_two_exec_time"] = round(interactor_response["player_two_exec_time"], 2)  # Round to 0.01 seconds
        result["player_two_exec_memory"] = round(interactor_response["player_two_exec_memory"] / 1048576, 2)  # Convert back to megabytes

    # In case the message is end-game status ('player1 did this' or 'player2 did that') replace with actual username
    result["message"] = result["message"].replace(PLAYER_ONE_NAME, player_one_name)
    result["message"] = result["message"].replace(PLAYER_TWO_NAME, player_two_name)

    updater.add_info(result=result)
    return run_result


def execute_two_player_game(submit_id, test: TestInfo, run_config: RunConfig,
                 player_one_executable_path, player_two_executable_path) -> RunResult:

    log_file = "interaction.log"
    tester_executable_name = "tester." + run_config.tester_path.split(".")[-1]
    player_one_executable_name = "solution1." + player_one_executable_path.split(".")[-1]
    player_two_executable_name = "solution2." + player_two_executable_path.split(".")[-1]

    args = {
        "tester_run_command": Runner.get_run_command(
            language=common.get_language_by_exec_name(tester_executable_name),
            executable=tester_executable_name,
            memory_limit=config.MAX_TESTER_MEMORY
        ),
        "player_one_run_command": Runner.get_run_command(
            language=common.get_language_by_exec_name(player_one_executable_name),
            executable=player_one_executable_name,
            memory_limit=run_config.memory_limit
        ),
        "player_two_run_command": Runner.get_run_command(
            language=common.get_language_by_exec_name(player_two_executable_name),
            executable=player_two_executable_name,
            memory_limit=run_config.memory_limit
        ),
        "tester_timeout": config.MAX_GAME_LENGTH,
        "solution_timeout": run_config.timeout,
        "time_limit": run_config.time_limit,
        "memory_limit": run_config.memory_limit,
        "log_file": log_file
    }

    empty_file = NamedTemporaryFile(mode="w+t", delete=False)
    empty_file_path = os.path.abspath(empty_file.name)

    # Copy all the needed files to the sandbox directory:
    #   1) wrapper.py
    #   2) the tester executable
    #   3) player one's solution executable
    #   4) player two's solution executable
    #   5) the input file (read by the tester)
    #   6) game log (empty file with write permissions)

    sandbox = Sandbox()
    sandbox.put_file(os.path.join(config.ROOT_DIR, "wrapper.py"))
    sandbox.put_file(run_config.tester_path, target_name=tester_executable_name)
    sandbox.put_file(player_one_executable_path, target_name=player_one_executable_name)
    sandbox.put_file(player_two_executable_path, target_name=player_two_executable_name)
    sandbox.put_file(test.inpPath, target_name="input.txt", mode=0o777)
    sandbox.put_file(empty_file_path, target_name=log_file, mode=0o777)

    # Run the executable, while measuring CPU and Memory consumption
    run_result = Runner.run_program(
        sandbox=sandbox,
        executable_path=os.path.join(config.ROOT_DIR, "interactor_2P.py"),
        memory_limit=config.MAX_EXECUTION_MEMORY,
        timeout=config.MAX_GAME_LENGTH,
        input_bytes=json.dumps(args, indent=4, sort_keys=True).encode()
    )
    interaction_log = sandbox.read_file(log_file)
    del sandbox

    # Record the game log to the /replays folder
    replay_id = save_replay_log(interaction_log)

    # The interactor crashed or got killed
    # Don't check memory limit, as it can be caused by child processes (including solution)
    if run_result.exec_time >= config.MAX_GAME_LENGTH:
        message = "Interactor took too much time to complete ({:.3f}s).".format(run_result.exec_time)
        logger.error("Submit {id} | {message}".format(id=submit_id, message=message))
        return RunResult(status=TestStatus.INTERNAL_ERROR, error=message, replay_id=replay_id)
    if run_result.exit_code != 0:
        message = "Interactor exited with non-zero exit code ({}).".format(run_result.exit_code)
        logger.error("Submit {id} | {message}".format(id=submit_id, message=message))
        return RunResult(status=TestStatus.INTERNAL_ERROR, error=message, replay_id=replay_id)

    # Parse the response from the interactor
    try:
        results = json.loads(run_result.output.decode(config.OUTPUT_ENCODING))
    except ValueError:
        message = "Could not decode interactor's output: {}!".format(run_result.output.decode())
        logger.error("Submit {id} | {message}".format(id=submit_id, message=message))
        return RunResult(status=TestStatus.INTERNAL_ERROR, error=message, replay_id=replay_id)

    # print("RESULTS:\n{}".format(json.dumps(results, indent=4, sort_keys=True)))

    # Okay, let's assume the interactor was okay. Now check if the tester crashed.
    if results["internal_error"]:
        message = results["message"]
        logger.error("Submit {id} | {message}".format(id=submit_id, message=message))
        return RunResult(status=TestStatus.INTERNAL_ERROR, error=message, replay_id=replay_id)

    # Everything with the system seems okay.
    # Leave the caller function decide what the test status will be
    return RunResult(status=TestStatus.ACCEPTED, output=run_result.output, replay_id=replay_id)
