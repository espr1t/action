import sys
import subprocess
import json
import psutil
import traceback
from time import perf_counter
from string import printable
from wrapper import COMMAND_WRAPPER, parse_exec_info

PLAYER_ONE_NAME = "player1"
PLAYER_TWO_NAME = "player2"
PROCESS_STOPPED_STATES = ["stopped", "zombie", "dead"]


def interact(tester_wrapped_cmd, player_one_wrapped_cmd, player_two_wrapped_cmd, solution_timeout,
             time_limit, memory_limit, input_text):
    message = "The game has not yet completed."
    player_one_score = player_one_exec_time = player_one_exec_memory = 0
    player_two_score = player_two_exec_time = player_two_exec_memory = 0

    sys.stderr.write("Starting tester process...\n")

    # Start the tester's process
    tester_process = psutil.Popen(
        args=tester_wrapped_cmd,
        shell=True,
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True
    )

    # Write the test input to the tester
    sys.stderr.write("Writing input data...\n")
    tester_process.stdin.write(input_text)
    tester_process.stdin.flush()

    # Start the game loop
    cur_move = 0
    stopped_prematurely = False
    internal_error = False

    while True:
        cur_move += 1

        # Determine player (alternating players every turn)
        ai_name = PLAYER_ONE_NAME if cur_move % 2 == 1 else PLAYER_TWO_NAME
        ai_run_cmd = player_one_wrapped_cmd if cur_move % 2 == 1 else player_two_wrapped_cmd
        sys.stderr.write("At move {} ({})\n".format(cur_move, ai_name))

        # Run the player's AI
        # Start the AI's process
        sys.stderr.write("Starting the AI process (command = {})\n".format(ai_run_cmd))
        ai_start_time = perf_counter()
        ai_process = psutil.Popen(
            args=ai_run_cmd,
            shell=True,
            stdin=tester_process.stdout,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True
        )
        sys.stderr.write("Started...\n")

        try:
            ai_process.wait(timeout=solution_timeout + 0.1)
            ai_exit_code, ai_exec_time, ai_exec_memory = parse_exec_info(ai_process.stderr.read(), solution_timeout)
        except psutil.TimeoutExpired:
            ai_process.kill()
            ai_exit_code, ai_exec_time, ai_exec_memory = -1, perf_counter() - ai_start_time, 0.0

        sys.stderr.write("  >> ai_exit_code = {}\n".format(ai_exit_code))
        sys.stderr.write("  >> ai_exec_time = {}\n".format(ai_exec_time))
        sys.stderr.write("  >> ai_exec_memory = {}\n".format(ai_exec_memory))

        # Tester has completed its execution in the meantime
        if tester_process.status() in PROCESS_STOPPED_STATES:
            sys.stderr.write("Tester stopped.\n")
            break

        # Update player's stats for time and memory
        if cur_move % 2 == 1:
            player_one_exec_time = max(player_one_exec_time, ai_exec_time)
            player_one_exec_memory = max(player_one_exec_memory, ai_exec_memory)
        else:
            player_two_exec_time = max(player_two_exec_time, ai_exec_time)
            player_two_exec_memory = max(player_two_exec_memory, ai_exec_memory)

        # Squash the output to one line only as tester has no way of knowing who printed the output
        ai_output = ai_process.stdout.read().strip().replace("\r", "").replace("\n", " ")
        sys.stderr.write("AI output: '{}'\n".format(ai_output))

        # Check if the output contains at least one printable character
        ai_output_empty = not any(ch in printable for ch in ai_output)

        # RE, TL, ML, or no output - stop the game and declare the other player as a winner
        if ai_exit_code != 0 or ai_exec_time > time_limit \
                or ai_exec_memory > memory_limit or ai_output_empty:
            if ai_exec_time > time_limit:
                message = "{}'s solution used more than the allowed {:.2f} seconds.".format(ai_name, time_limit)
            elif ai_exec_memory > memory_limit:
                message = "{}'s solution used more than the allowed {:.0f} bytes.".format(ai_name, memory_limit)
            elif ai_exit_code != 0:
                message = "{}'s solution crashed.".format(ai_name)
            elif ai_output_empty:
                message = "{}'s solution did not print any output.".format(ai_name)

            player_one_score = 0.0 if cur_move % 2 == 1 else 1.0
            player_two_score = 0.0 if cur_move % 2 == 0 else 1.0

            tester_process.kill()
            stopped_prematurely = True
            break

        # Print the output and ensure there is a new line at the end
        try:
            tester_process.stdin.write(ai_output + "\n")
            tester_process.stdin.flush()
        except BrokenPipeError:
            break

    """
    If the game ended properly the tester should have printed the results in the format:
        >> line 1: status (OK/WA/IE...)
        >> line 2: score player one (floating point number)
        >> line 3: score player two (floating point number)
        >> line 4: message (optional)
    """

    if not stopped_prematurely:
        try:
            tester_exit_code = tester_process.wait(0.2)
        except psutil.TimeoutExpired:
            tester_process.kill()
            tester_exit_code = -1

        if tester_exit_code == 0:
            # Parse the tester's output
            final_message = tester_process.stderr.read().split("\n")
            sys.stderr.write("Tester final message: {}\n".format(final_message))

            if len(final_message) < 3:
                internal_error = True
                message = "Tester printed less than 3 lines: {}".format(final_message)
            else:
                if final_message[0].strip()[:2] not in ["OK", "WA", "TL", "IE"]:
                    internal_error = True
                    message = "Tester didn't print status on the first line!"
                else:
                    try:
                        player_one_score = float(final_message[1])
                        player_two_score = float(final_message[2])
                    except ValueError:
                        internal_error = True
                        message = "Tester didn't print players scores on second and third line ('{}' and '{})!"\
                            .format(final_message[1], final_message[2])
                    if len(final_message) >= 4:
                        message = final_message[3]
                        for replacement in ["Player1", "player1", "First player", "first player", "Player one", "player one"]:
                            message = message.replace(replacement, PLAYER_ONE_NAME)
                        for replacement in ["Player2", "player2", "Second player", "second player", "Player two", "player two"]:
                            message = message.replace(replacement, PLAYER_TWO_NAME)
        else:
            internal_error = True
            message = "Tester exited with non-zero exit code ({}).".format(tester_exit_code)

    sys.stderr.write("Writing results...\n")
    sys.stdout.write(json.dumps({
        "message": message,
        "internal_error": internal_error,
        "player_one_score": player_one_score,
        "player_one_exec_time": player_one_exec_time,
        "player_one_exec_memory": player_one_exec_memory,
        "player_two_score": player_two_score,
        "player_two_exec_time": player_two_exec_time,
        "player_two_exec_memory": player_two_exec_memory
    }, indent=4, sort_keys=True) + "\n")

    sys.stderr.write("Done.\n")


def prepare_and_run(args, input_text):
    tester_wrapped_cmd = COMMAND_WRAPPER.format(timeout=args["tester_timeout"],
                                                command=args["tester_run_command"] + " " + args["log_file"])
    tester_wrapped_cmd = tester_wrapped_cmd.replace(" 2>/dev/null", "")
    player_one_wrapped_cmd = COMMAND_WRAPPER.format(timeout=args["solution_timeout"],
                                                    command=args["player_one_run_command"])
    player_two_wrapped_cmd = COMMAND_WRAPPER.format(timeout=args["solution_timeout"],
                                                    command=args["player_two_run_command"])

    interact(
        tester_wrapped_cmd=tester_wrapped_cmd,
        player_one_wrapped_cmd=player_one_wrapped_cmd,
        player_two_wrapped_cmd=player_two_wrapped_cmd,
        solution_timeout=args["solution_timeout"],
        time_limit=args["time_limit"],
        memory_limit=args["memory_limit"],
        input_text=input_text
    )


def prod():
    try:
        sys.stderr.write("Starting...\n")
        args = json.loads(sys.stdin.read())
        with open("input.txt", "rt") as inp:
            input_text = inp.read()
        with open("input.txt", "wt") as out:
            out.write("Too late.")
        prepare_and_run(args=args, input_text=input_text)

    except Exception as ex:
        sys.stderr.write("Got exception {}...\n".format(ex))
        sys.stdout.write(json.dumps({
            "internal_error": True,
            "tester_message": traceback.format_exc()
        }, indent=4, sort_keys=True) + "\n")


if __name__ == "__main__":
    prod()
