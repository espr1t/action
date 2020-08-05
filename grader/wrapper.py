from signal import SIGKILL
from logging import Logger

# Redirect the run_command's stderr to /dev/null, but keep the output from /usr/bin/time (which is also stderr)
# Send a SIGKILL signal after {timeout} seconds to get the program killed
# http://man7.org/linux/man-pages/man1/time.1.html
# --format argument '%U' prints "Elapsed CPU seconds in User mode"
# --format argument '%S' prints "Total number of CPU-seconds that the process spent in kernel mode."
# --format argument '%e' prints "Elapsed real (clock) time in seconds"
# --format argument '%M' prints "Maximum resident set size (kbytes)"
COMMAND_WRAPPER = \
    "{time_cmd} /bin/bash -c \"{timeout_cmd} /bin/bash -c \\\"{{command}}\\\" 2>/dev/null\" ; {exit_cmd}".format(
        time_cmd="/usr/bin/time --quiet --format='%U %S %e %M'",
        timeout_cmd="/usr/bin/timeout --preserve-status --signal=SIGKILL {timeout:.3f}s",
        exit_cmd="code=$? ; >&2 echo $code ; exit $code"
    )


def parse_exec_info(info: str, timeout: float, logger: Logger = None):
    """
    Parses the output of /usr/bin/time and the exit code.
    :param info: A string containing the stderr output from running the above command
    :param timeout: A float, the timeout used in the above command
    :param logger: A logger to use, or None if no logging required
    :return: A tuple (exit_code, exec_time, exec_memory) or None
    """
    # print("{sep}\n                 Parsing output\n{sep}\n{info}".format(sep="="*50, info=info))

    info_lines = info.splitlines()

    if len(info_lines) < 2:
        if logger is not None:
            logger.error("Expected info_lines to contain at least two lines; got {}".format(len(info_lines)))
        return None

    try:
        # Fix exit code (it is offset by 128 by /usr/bin/timeout command)
        exit_code = int(info_lines[-1]) % 128

        tokens = info_lines[-2].strip().split()
        # Execution time is user time + sys time
        exec_time = float(tokens[0]) + float(tokens[1])
        clock_time = float(tokens[2])
        # Memory is recorded in kilobytes, thus convert it back to megabytes
        exec_memory = int(tokens[3]) * 1024
    except (ValueError, IndexError):
        if logger is not None:
            logger.error("Could not parse exec info from string: {}".format("|".join(info_lines[-2:])))
        return None

    # If the program was killed, but the user+sys time is small, use clock time instead
    # This can happen if the program sleeps, for example, or blocks on a resource it never gets
    if exit_code == SIGKILL and exec_time < timeout:
        exec_time = clock_time

    if clock_time > 0.5 and abs(exec_time - clock_time) > 0.2 * clock_time:
        if logger is not None:
            logger.warning("Returned execution time differs from clock time by more than 20% ({:.3f}s vs. {:.3f}s)."
                    .format(exec_time, clock_time))

    # Return the exit code, execution time and execution memory as a tuple
    return exit_code, exec_time, exec_memory
