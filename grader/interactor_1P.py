import sys
import subprocess
import json
import psutil
from wrapper import COMMAND_WRAPPER, parse_exec_info


def parse_output(prefix, stderr, timeout):
    result = parse_exec_info(stderr, timeout)
    return {} if result is None else {
        prefix + "_exit_code": result[0],
        prefix + "_exec_time": result[1],
        prefix + "_exec_memory": result[2],
        prefix + "_message": "\n".join(stderr.strip().splitlines()[:-2])
    }


def parse_info(tester_stderr, solution_stderr, tester_timeout, solution_timeout, solution_exited_unexpectedly):
    info = {}
    info.update(parse_output(prefix="tester", stderr=tester_stderr, timeout=tester_timeout))
    info.update(parse_output(prefix="solution", stderr=solution_stderr, timeout=solution_timeout))

    # We were the ones who killed the tester, as the solution exited unexpectedly
    if "tester_exit_code" not in info or info["tester_exit_code"] != 0:
        if solution_exited_unexpectedly:
            info["tester_exit_code"] = 0
            info["tester_exec_time"] = 0.0
            info["tester_exec_memory"] = 0
            info["tester_message"] = "WA\n0.0\nSolution exited unexpectedly."

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
    return info


def interact(tester_wrapped_cmd, solution_wrapped_cmd, tester_timeout, solution_timeout, extra_time, input_text):
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

    sys.stderr.write("Writing input data...\n")
    # Write the input file to the tester
    tester_process.stdin.write(input_text)
    tester_process.stdin.flush()

    sys.stderr.write("Starting solution process...\n")
    # Start the solution's process (piping the tester's input and output directly)
    solution_process = psutil.Popen(
        args=solution_wrapped_cmd,
        shell=True,
        stdin=tester_process.stdout,
        stdout=tester_process.stdin,
        stderr=subprocess.PIPE,
        universal_newlines=True
    )

    solution_exited_unexpectedly = False
    if solution_process.wait() != 0:
        solution_exited_unexpectedly = True
    else:
        try:
            # Give the tester time to print the interaction log and finish its execution
            tester_process.wait(timeout=extra_time)
        except psutil.TimeoutExpired:
            # If the tester doesn't finish in one more second then it is most likely waiting
            # on input from the solution. It may be other issue, but let's assume the tester
            # is written properly and this is not the case.
            solution_exited_unexpectedly = True

    if solution_exited_unexpectedly:
        # Apparently the solution process died unexpectedly, but the tester is still waiting
        # for its output. Kill it immediately, along with its children (if any).
        for process in tester_process.children(recursive=True):
            process.kill()
        tester_process.kill()

    tester_stderr = tester_process.stderr.read()
    solution_stderr = solution_process.stderr.read()

    sys.stderr.write("Parsing output...\n")
    info = parse_info(
        tester_stderr=tester_stderr,
        solution_stderr=solution_stderr,
        tester_timeout=tester_timeout,
        solution_timeout=solution_timeout,
        solution_exited_unexpectedly=solution_exited_unexpectedly
    )

    sys.stderr.write("Writing results...\n")
    sys.stdout.write(json.dumps(info, indent=4, sort_keys=True) + "\n")

    sys.stderr.write("Done.\n")


def prepare_and_run(tester_run_cmd, solution_run_cmd, time_limit, input_text, log_file):
    # Both the tester and the solution get extra time in order to account for time tester
    # processes input/output. The tester gets additional time to make sure it is killed after
    # the solution.
    extra_time = min(1.0, time_limit)
    solution_timeout = time_limit + max(0.2, time_limit * 0.2) + extra_time
    tester_timeout = solution_timeout + extra_time

    tester_wrapped_cmd = COMMAND_WRAPPER.format(timeout=tester_timeout, command=tester_run_cmd + " " + log_file)
    tester_wrapped_cmd = tester_wrapped_cmd.replace(" 2>/dev/null", "")
    solution_wrapped_cmd = COMMAND_WRAPPER.format(timeout=solution_timeout, command=solution_run_cmd)
    interact(
        tester_wrapped_cmd=tester_wrapped_cmd,
        solution_wrapped_cmd=solution_wrapped_cmd,
        tester_timeout=tester_timeout,
        solution_timeout=solution_timeout,
        extra_time=extra_time,
        input_text=input_text
    )


def prod():
    args = json.loads(sys.stdin.read())
    with open("input.txt", "rt") as inp:
        input_text = inp.read()
    with open("input.txt", "wt") as out:
        out.write("Too late.")
    prepare_and_run(
        tester_run_cmd=args["tester_run_command"],
        solution_run_cmd=args["solution_run_command"],
        time_limit=args["time_limit"],
        input_text=input_text,
        log_file=args["log_file"]
    )


if __name__ == "__main__":
    prod()
