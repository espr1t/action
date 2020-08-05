"""
Supports game execution.
Simultaneously runs a tester program along with several (at least two) users' solutions.
The users' solution communicate with the tester program through stdin/stdout and the tester
verifies their outputs at each "move". Solutions are executed anew for each move.

Requires two Sandbox instances (workers): the tester runs in one and the solutions are ran in another.
"""

import os
from string import printable
from time import sleep, perf_counter
from tempfile import NamedTemporaryFile

import common
from common import TestStatus, TestInfo
import config
from runner import Runner, RunConfig, RunResult
from sandbox import Sandbox

logger = common.get_logger(__file__)


def execute_game(updater, submit_id, result_id, test: TestInfo, run_config: RunConfig,
                 player_one_id, player_one_name, player_one_executable_path,
                 player_two_id, player_two_name, player_two_executable_path):
    # Prepare the run input and output data
    logger.info("Submit {} | Test {}: {} vs {} (result_id = {})...".format(
        submit_id, test.position, player_one_name, player_two_name, result_id))

    player_one_score = 0
    player_two_score = 0
    player_one_exec_time = 0
    player_one_exec_memory = 0
    player_two_exec_time = 0
    player_two_exec_memory = 0
    message = "The game has not yet completed."

    # Setup tester and players's run configurations
    tester_run_config = RunConfig(
        executable_path=run_config.tester_path,
        time_limit=config.MAX_GAME_LENGTH,
        memory_limit=config.MAX_TESTER_MEMORY
    )

    player_one_run_config = RunConfig(
        executable_path=player_one_executable_path,
        time_limit=run_config.time_limit,
        memory_limit=run_config.memory_limit
    )

    player_two_run_config = RunConfig(
        executable_path=player_two_executable_path,
        time_limit=run_config.time_limit,
        memory_limit=run_config.memory_limit
    )

    # Start running the tester's process
    tester_sandbox = Sandbox()
    tester_stdin, tester_stdout, tester_stderr = os.pipe(), os.pipe(), os.pipe()
    tester_command = Runner.get_run_command(
            language=Runner.get_language_by_exec_name(tester_run_config.executable_path),
            executable=tester_run_config.executable_path,
            memory_limit=tester_run_config.memory_limit
    )
    tester_command = tester_command.replace("2> /dev/null", "")
    tester_sandbox.execute(
            command=tester_command,
            stdin_fd=tester_stdin[0],
            stdout_fd=tester_stdout[1],
            stderr_fd=tester_stderr[1],
            blocking=False
    )

    # Open the tester's input and output without buffering
    # TODO: Check if we can pass the stdin/stdout directly to the AI process.
    #       We should verify that it is not able to read the input of the task
    #       (which is also written there).
    #       We should also verify that writing multiple outputs doesn't trick
    #       the tester it's the opponent's output.
    tester_input = open(tester_stdin[1], mode="wb", buffering=0)
    tester_output = open(tester_stdout[0], mode="rb", buffering=0)

    # Write the test input to the tester
    tester_input.write(open(test.inpPath, mode="rb").read())
    tester_input.flush()

    # Start the game loop
    cur_move = 0
    stopped_prematurely = False

    while True:
        sleep(0.01)
        # Process already terminated
        if not tester_sandbox.is_running():
            break

        cur_move += 1

        # Determine player (alternating players every turn)
        player = player_one_name if cur_move % 2 == 1 else player_two_name
        ai_run_config = player_one_run_config if cur_move % 2 == 1 else player_two_run_config

        # Run the player's AI
        # ai_run_result = runner.run(run_config=ai_run_config, input_bytes=tester_output.read())
        player_sandbox = Sandbox()
        ai_run_result = Runner.run_program(
            sandbox=player_sandbox,
            executable_path=ai_run_config.executable_path,
            memory_limit=ai_run_config.memory_limit,
            timeout=ai_run_config.timeout,
            input_bytes=tester_output.read()
        )

        # Update player's stats for time and memory
        if cur_move % 2 == 1:
            player_one_exec_time = max(player_one_exec_time, ai_run_result.exec_time)
            player_one_exec_memory = max(player_one_exec_memory, ai_run_result.exec_memory)
        else:
            player_two_exec_time = max(player_two_exec_time, ai_run_result.exec_time)
            player_two_exec_memory = max(player_two_exec_memory, ai_run_result.exec_memory)

        # Check if the output contains at least one printable character
        ai_output_text = ai_run_result.output.decode(encoding=config.OUTPUT_ENCODING).strip().replace("\r", "")
        ai_output_empty = not any(ch in printable for ch in ai_output_text)

        # RE, TL, ML, or no output - stop the game and declare the other player as a winner
        if ai_run_result.exit_code != 0 or ai_run_result.exec_time > ai_run_config.time_limit \
                or ai_run_result.exec_memory > ai_run_config.memory_limit or ai_output_empty:
            if ai_run_result.exec_time > ai_run_config.time_limit:
                message = "{}'s solution used more than the allowed {:.2f} seconds.".format(
                    player, ai_run_config.time_limit)
                logger.info("Submit {} | Test {}: {}".format(submit_id, test.position, message))
            elif ai_run_result.exec_memory > ai_run_config.memory_limit:
                message = "{}'s solution used more than the allowed {:.0f} megabytes.".format(
                    player, ai_run_config.memory_limit / 1048576)
                logger.info("Submit {} | Test {}: {}".format(submit_id, test.position, message))
            elif ai_run_result.exit_code != 0:
                message = "{}'s solution crashed.".format(player)
                logger.info("Submit {} | Test {}: {}".format(submit_id, test.position, message))
            elif ai_output_empty:
                message = "{}'s solution did not print any output.".format(player)
                logger.info("Submit {} | Test {}: {}".format(submit_id, test.position, message))

            player_one_score = 0.0 if player == player_one_name else 1.0
            player_two_score = 0.0 if player == player_two_name else 1.0

            tester_sandbox.wait(timeout=0.1)
            stopped_prematurely = True
            break

        # Print the output and ensure there is a new line at the end
        # Limiting to one line only as tester has no way of knowing who printed the output
        tester_input.write((ai_output_text.splitlines()[0] + "\n").encode(encoding=config.OUTPUT_ENCODING))
        tester_input.flush()
        # Print only the first line of output and ensure there is a new line at the end

    run_result = RunResult(status=TestStatus.ACCEPTED, message="", exit_code=0)

    """
    If the game ended properly the tester should have printed the results in the format:
        >> line 1: score player one
        >> line 2: score player two
        >> line 3: message
    """
    if not stopped_prematurely:
        tester_run_info = tester_sandbox.wait_and_get_info()
        if tester_run_info.exit_code != 0:
            message = "Game tester exited with non-zero exit code ({}).".format(tester_run_info.exit_code)
            logger.error("[Submission {}] Internal Error: {}".format(submit_id, message))
            run_result = runner.RunResult(status=TestStatus.INTERNAL_ERROR, message=message)

        # Parse the tester's output
        final_message = tester_output.read().decode(config.OUTPUT_ENCODING).split("\n")
        player_one_score = float(final_message[0])
        player_two_score = float(final_message[1])
        run_result.message = final_message[2]
        replacements_player_one = ["First player", "first player", "Player one", "player one"]
        replacements_player_two = ["Second player", "second player", "Player two", "player two"]
        for replacement in replacements_player_one:
            run_result.message = run_result.message.replace(replacement, player_one_name)
        for replacement in replacements_player_two:
            run_result.message = run_result.message.replace(replacement, player_two_name)

    tester_input.close()
    tester_output.close()

    match_log = tester_sandbox.read_file("match.log").decode(encoding=config.OUTPUT_ENCODING)

    # Update the frontend
    updater.add_info(message="", result={
        "id": result_id,
        "position": test.position,
        "status": run_result.status.name,
        "message": run_result.message,
        "player_one_id": player_one_id,
        "player_one_score": player_one_score,
        "player_one_exec_time": player_one_exec_time,
        "player_one_exec_memory": round(player_one_exec_memory / 1048576.0, 2),  # Convert back to megabytes
        "player_two_id": player_two_id,
        "player_two_score": player_two_score,
        "player_two_exec_time": player_two_exec_time,
        "player_two_exec_memory": round(player_two_exec_memory / 1048576.0, 2),  # Convert back to megabytes
        "match_log": match_log
    })
    return run_result
