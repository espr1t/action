import sys
import subprocess
import json
import psutil
from signal import SIGKILL, SIGTERM

# Redirect the run_command's stderr to /dev/null, but keep the output from /usr/bin/time (which is also stderr)
# http://man7.org/linux/man-pages/man1/time.1.html
# --format argument '%U' prints "Elapsed CPU seconds in User mode"
# --format argument '%S' prints "Total number of CPU-seconds that the process spent in kernel mode."
# --format argument '%e' prints "Elapsed real (clock) time in seconds"
# --format argument '%M' prints "Maximum resident set size (kbytes)"
# --format argument '%x' prints "Exit status of the command."
# Send a SIGTERM signal after {timeout} seconds, but ensure the program is killed after 0.2 more seconds
RUN_TEST_COMMAND = "{time_cmd} /bin/bash -c \"{timeout_cmd} /bin/bash -c \\\"{{command}}\\\" 2> /dev/null\"".format(
    time_cmd="/usr/bin/time --quiet --format='%U %S %e %M %x'",
    timeout_cmd="/usr/bin/timeout --preserve-status --kill-after=0.2s --signal=SIGTERM {timeout}s"
)


def parse_time_memory_info(info_line, timeout):
    # Get time, memory and exit_code info from /usr/bin/time output
    tokens = info_line.split()

    # Fix exit code (it is offset by 128 by /usr/bin/timeout command)
    exit_code = 0 if int(tokens[4]) == 0 else int(tokens[4]) - 128

    # Exec time is user time + sys time
    exec_time = float(tokens[0]) + float(tokens[1])
    clock_time = float(tokens[2])
    exec_memory = int(tokens[3]) * 1024

    # If program was killed, use total (clock) time instead
    if exit_code == SIGKILL or exit_code == SIGTERM:
        if exec_time < timeout:
            exec_time = clock_time
    return exit_code, exec_time, exec_memory


def parse_output(prefix, stderr, timeout):
    stderr_lines = stderr.strip().splitlines()
    if len(stderr_lines) >= 1 and stderr_lines[0].strip() == "Killed":
        stderr_lines = stderr_lines[1:]

    info = {}
    if len(stderr_lines) >= 1:
        exit_code, exec_time, exec_memory = parse_time_memory_info(stderr_lines[-1], timeout)
        info[prefix + "_exit_code"] = exit_code
        info[prefix + "_exec_time"] = exec_time
        info[prefix + "_exec_memory"] = exec_memory
        info[prefix + "_message"] = "\n".join(stderr_lines[:-1])
    return info


def parse_info(tester_stderr, solution_stderr, timeout, solution_exited_unexpectedly):
    info = {}
    info.update(parse_output("tester", tester_stderr, timeout))
    info.update(parse_output("solution", solution_stderr, timeout))

    # We were the ones who killed the tester, as the solution exited unexpectedly
    if solution_exited_unexpectedly:
        info["tester_exit_code"] = 0
        if "tester_exec_time" not in info:
            info["tester_exec_time"] = 0.0
        info["tester_score"] = 0.0
        info["tester_info_message"] = "Solution exited unexpectedly."

    # Sanity checking
    info["internal_error"] = False
    if not info["internal_error"] and "tester_exit_code" not in info:
        info["internal_error"] = True
    if not info["internal_error"] and "tester_exec_time" not in info:
        info["internal_error"] = True
    if not info["internal_error"] and "solution_exit_code" not in info:
        info["internal_error"] = True
    if not info["internal_error"] and "solution_exec_time" not in info:
        info["internal_error"] = True

    if info["internal_error"]:
        return info

    # Fix known cases that have issues
    # TODO: Fix this in a better way
    if info["solution_exit_code"] == 143 and info["tester_exit_code"] == 143:
        if info["solution_exec_time"] < info["tester_exec_time"] - 0.2:
            info["solution_exit_code"] = 0
        else:
            info["tester_exit_code"] = 0

    if info["solution_exit_code"] != 0 and info["solution_exit_code"] != 143:
        info["tester_exit_code"] = 0

    return info


def interact(solution_exec_cmd, tester_exec_cmd, tester_input, timeout):
    sys.stderr.write("Starting tester process...\n")
    # Start the tester's process

    tester_process = psutil.Popen(
        args=tester_exec_cmd,
        shell=True,
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        universal_newlines=True
    )

    sys.stderr.write("Writing input data...\n")
    # Write the input file to the tester
    tester_process.stdin.write(tester_input)
    tester_process.stdin.flush()

    sys.stderr.write("Starting solution process...\n")
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

    tester_stderr = tester_process.stderr.read_file()
    solution_stderr = solution_process.stderr.read_file()

    info = parse_info(tester_stderr, solution_stderr, timeout, solution_exited_unexpectedly)

    sys.stderr.write("Writing results.\n")
    sys.stdout.write(json.dumps(info, indent=4, sort_keys=True) + "\n")


def prepare_and_run(timeout, tester_run_cmd, solution_run_cmd):
    tester_input = sys.stdin.read_file()

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
    interact(solution_exec_cmd, tester_exec_cmd, tester_input, timeout)


def prod():
    with open("args.json", "rt") as inp:
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
