import subprocess
import json

RUN_TEST_COMMAND = "{time} /bin/bash -c \"{timeout} {command}\" ; >&2 printf '%d' $?".format(
    time="/usr/bin/time --quiet --format='%U %e %M'",
    # Send a SIGTERM signal after {timeout} seconds, but ensure the program is killed after 0.2 more seconds
    timeout="/usr/bin/timeout --preserve-status --kill-after=0.2s --signal=SIGTERM {timeout}s",
    command="{run_command} 2> /dev/null"
)


def parse_output(prefix, stderr):
    stderr_lines = stderr.strip().splitlines()
    info = {}
    if len(stderr_lines) >= 1:
        info[prefix + "_exit_code"] = int(stderr_lines[-1])
    if len(stderr_lines) >= 2:
        tokens = stderr_lines[-2].split()
        info[prefix + "_user_time"] = float(tokens[0])
        info[prefix + "_clock_time"] = float(tokens[1])
        info[prefix + "_memory"] = int(tokens[2])
    if len(stderr_lines) >= 3 and info[prefix + "_exit_code"] == 0:
        info[prefix + "_info_message"] = stderr_lines[-3]
    if len(stderr_lines) >= 4 and info[prefix + "_exit_code"] == 0:
        info[prefix + "_score"] = float(stderr_lines[-4])
    return info


def parse_info(tester_stderr, solution_stderr):
    # print("Tester stderr:\n{}".format(tester_stderr))
    # print("Solution stderr:\n{}".format(solution_stderr))

    info = {}
    info.update(parse_output("tester", tester_stderr))
    info.update(parse_output("solution", solution_stderr))

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
    if info["solution_exit_code"] == 143:
        if info["tester_exit_code"] == 143:
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
    tester_process = subprocess.Popen(
        args=tester_exec_cmd,
        shell=True,
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True)

    print("Writing input data...")
    # Write the input file to the tester
    tester_process.stdin.write(tester_input)
    tester_process.stdin.flush()

    print("Starting solution process...")
    # Start the solution's process (piping the tester's input and output directly)
    solution_process = subprocess.Popen(
        args=solution_exec_cmd,
        shell=True,
        stdin=tester_process.stdout,
        stdout=tester_process.stdin,
        stderr=subprocess.PIPE,
        universal_newlines=True)

    solution_process.wait()
    tester_process.wait()
    """
    try:
        tester_process.wait(timeout=0.5)
    except subprocess.TimeoutExpired:
        # Apparently the child process died
        tester_process.kill()
        # os.killpg(tester_process.pid, SIGKILL)
        pass
    """

    tester_stderr = tester_process.stderr.read()
    solution_stderr = solution_process.stderr.read()

    info = parse_info(tester_stderr, solution_stderr)

    print("Writing results.")
    with open("results.txt", "wt") as out:
        out.write(json.dumps(info, indent=4, sort_keys=True) + "\n")
    print(json.dumps(info, indent=4, sort_keys=True))


def prepare_and_run(timeout, tester_run_cmd, solution_run_cmd):
    with open("input.txt", "rt") as inp:
        tester_input = inp.read()
    with open("input.txt", "wt") as out:
        out.write("Deleted.\n")

    tester_exec_cmd = RUN_TEST_COMMAND.format(
        timeout=timeout + 0.5, run_command=tester_run_cmd
    )
    tester_exec_cmd = tester_exec_cmd.replace(" 2> /dev/null", "")

    solution_exec_cmd = RUN_TEST_COMMAND.format(
        timeout=timeout, run_command=solution_run_cmd
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
