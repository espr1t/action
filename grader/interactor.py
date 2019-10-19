import subprocess
import json
import psutil

RUN_TEST_COMMAND = "{time} /bin/bash -c \"{timeout} {command}\" ; >&2 printf '%d' $?".format(
    time="/usr/bin/time --quiet --format='%U %S %e %M'",
    # Send a SIGTERM signal after {timeout} seconds, but ensure the program is killed after 0.2 more seconds
    timeout="/usr/bin/timeout --preserve-status --kill-after=0.2s --signal=SIGTERM {timeout}s",
    command="{run_command} 2> /dev/null"
)


def parse_output(prefix, stderr):
    stderr_lines = stderr.strip().splitlines()
    if len(stderr_lines) >= 1 and stderr_lines[0].strip() == "Killed":
        stderr_lines = stderr_lines[1:]

    info = {}
    if len(stderr_lines) >= 1:
        info[prefix + "_exit_code"] = int(stderr_lines[-1])
    if len(stderr_lines) >= 2:
        tokens = stderr_lines[-2].split()
        info[prefix + "_user_time"] = float(tokens[0])
        info[prefix + "_sys_time"] = float(tokens[1])
        info[prefix + "_clock_time"] = float(tokens[2])
        info[prefix + "_memory"] = int(tokens[3])
    if len(stderr_lines) >= 3 and info[prefix + "_exit_code"] == 0:
        info[prefix + "_info_message"] = stderr_lines[-3]
    if len(stderr_lines) >= 4 and info[prefix + "_exit_code"] == 0:
        info[prefix + "_score"] = float(stderr_lines[-4])
    return info


def parse_info(tester_stderr, solution_stderr, solution_exited_unexpectedly):
    info = {}
    info.update(parse_output("tester", tester_stderr))
    info.update(parse_output("solution", solution_stderr))

    # We were the ones who killed the tester, as the solution exited unexpectedly
    if solution_exited_unexpectedly:
        info["tester_exit_code"] = 0
        if "tester_user_time" not in info:
            info["tester_user_time"] = 0.0
        if "tester_clock_time" not in info:
            info["tester_clock_time"] = 0.0
        info["tester_score"] = 0.0
        info["tester_info_message"] = "Solution exited unexpectedly."

    # Sanity checking
    info["internal_error"] = False
    if not info["internal_error"] and "tester_exit_code" not in info:
        info["internal_error"] = True
    if not info["internal_error"] and "solution_exit_code" not in info:
        info["internal_error"] = True
    if not info["internal_error"] and "tester_user_time" not in info:
        info["internal_error"] = True
    if not info["internal_error"] and "solution_user_time" not in info:
        info["internal_error"] = True

    if info["internal_error"]:
        return info

    # Fix known cases that have issues
    # TODO: Fix this in a better way
    if info["solution_exit_code"] == 143 and info["tester_exit_code"] == 143:
        if info["solution_user_time"] < info["tester_user_time"] - 0.2:
            info["solution_exit_code"] = 0
            info["solution_clock_time"] = info["solution_user_time"]
        else:
            info["tester_exit_code"] = 0
            info["tester_clock_time"] = info["tester_user_time"]

    if info["solution_exit_code"] != 0 and info["solution_exit_code"] != 143:
        info["tester_exit_code"] = 0
        info["tester_clock_time"] = info["tester_user_time"]

    return info


def interact(solution_exec_cmd, tester_exec_cmd, tester_input):
    print("Starting tester process...")
    # Start the tester's process

    tester_process = psutil.Popen(
        args=tester_exec_cmd,
        shell=True,
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True
    )

    print("Writing input data...")
    # Write the input file to the tester
    tester_process.stdin.write(tester_input)
    tester_process.stdin.flush()

    print("Starting solution process...")
    # Start the solution's process (piping the tester's input and output directly)
    solution_process = psutil.Popen(
        args=solution_exec_cmd,
        shell=True,
        stdin=tester_process.stdout,
        stdout=tester_process.stdin,
        stderr=subprocess.PIPE,
        universal_newlines=True
    )

    solution_process.wait()
    solution_exited_unexpectedly = False
    try:
        tester_process.wait(timeout=1.0)
    except psutil.TimeoutExpired:
        # Apparently the solution process died unexpectedly,
        # but the tester is still waiting for its output
        for process in tester_process.children(recursive=True):
            process.kill()
        tester_process.kill()
        solution_exited_unexpectedly = True

    tester_stderr = tester_process.stderr.read()
    solution_stderr = solution_process.stderr.read()

    info = parse_info(tester_stderr, solution_stderr, solution_exited_unexpectedly)

    print("Writing results.")
    with open("results.txt", "wt") as out:
        out.write(json.dumps(info, indent=4, sort_keys=True) + "\n")
    print(json.dumps(info, indent=4, sort_keys=True))


def prepare_and_run(timeout, tester_run_cmd, solution_run_cmd):
    with open("input.txt", "rt") as inp:
        tester_input = inp.read()
    with open("input.txt", "wt") as out:
        out.write("Deleted.\n")

    # Both the tester and the solution get 1 extra second
    # (to account for time the tester actually processes input/output)
    # The tester gets additionally 0.5s to make sure it is killed after the solution
    tester_exec_cmd = RUN_TEST_COMMAND.format(
        timeout=timeout + 1.5, run_command=tester_run_cmd
    )
    tester_exec_cmd = tester_exec_cmd.replace(" 2> /dev/null", "")

    solution_exec_cmd = RUN_TEST_COMMAND.format(
        timeout=timeout + 1.0, run_command=solution_run_cmd
    )
    interact(solution_exec_cmd, tester_exec_cmd, tester_input)


def prod():
    with open("args.txt", "rt") as inp:
        args = json.loads(inp.read())

    # The first line contains the time limit
    timeout = float(args["timeout"])
    # The second line contains the exec command for the tester
    tester_run_cmd = args["tester_run_command"]
    # The third line contains the exec command for the solution
    solution_run_cmd = args["solution_run_command"]

    prepare_and_run(timeout, tester_run_cmd, solution_run_cmd)


def local():
    time_limit = 5.0
    tester_run_cmd = "./ImageScannerTester"
    solution_run_cmd = "./ImageScanner"
    prepare_and_run(time_limit, tester_run_cmd, solution_run_cmd)


if __name__ == "__main__":
    # local()
    prod()
