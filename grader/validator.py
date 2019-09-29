"""
Validates whether a given output is valid or not.
This can happen in several different ways:
    >> Direct text comparison between the expected output and the user's output
    >> Comparison between floats (if we detect they are floats and the flag is set in the problem)
    >> Using a checker
"""

import subprocess
from os import getcwd
from math import fabs
import config
import common
from status import TestStatus

logger = common.get_logger(__name__)


class Validator:

    @staticmethod
    def determine_status(submit_id, test, result, time_limit, memory_limit, inp_file, out_file, sol_file,
                         checker, floats_comparison):
        """
        Determines the proper execution status (OK, WA, TL, ML, RE) and score of the solution
        """
        # TODO: We can be more specific what the RE actually is:
        # Killed (TL): Command terminated by signal 9
        # Killed (TL): Killed (Command exited with non-zero status 137)
        # Killed (RE, division by zero): Command terminated by signal 8
        # Killed (RE, division by zero): Floating point exception (Command exited with non-zero status 136)
        # Killed (RE, out of bounds): Command terminated by signal 11
        # Killed (RE, out of bounds): Segmentation fault (Command exited with non-zero status 139)
        # Killed (RE, allocated too much memory): Command terminated by signal 11
        # Killed (RE, allocated too much memory): Segmentation fault (Command exited with non-zero status 139)
        # Killed (RE, max output size exceeded): Command terminated by signal 25
        # Killed (RE, max output size exceeded): File size limit exceeded (Command exited with non-zero status 153)

        # IE (Internal Error)
        if result.error_message != "":
            logger.error("Submit {} | Got error while executing test {}: \"{}\"".format(
                submit_id, test["inpFile"], result.error_message))
            return TestStatus.INTERNAL_ERROR, result.error_message, 0, ""

        # TL (Time Limit)
        if result.exec_time > time_limit:
            return TestStatus.TIME_LIMIT, "", 0, ""

        # ML (Memory Limit)
        if result.exec_memory > memory_limit:
            return TestStatus.MEMORY_LIMIT, "", 0, ""

        # RE (Runtime Error)
        if result.exit_code != 0:
            return TestStatus.RUNTIME_ERROR, "", 0, ""

        # AC (Accepted) or WA (Wrong Answer)
        error_message, score, info = Validator.validate_output(
            submit_id, inp_file, out_file, sol_file, floats_comparison, checker)
        if error_message != "":
            return TestStatus.WRONG_ANSWER, error_message, 0, info
        else:
            return TestStatus.ACCEPTED, "", score, info

    @staticmethod
    def validate_output(submit_id, inp_file, out_file, sol_file, floats_comparison, checker):
        if checker is None:
            return Validator.validate_output_directly(submit_id, out_file, sol_file, floats_comparison)
        else:
            return Validator.validate_output_with_checker(submit_id, inp_file, out_file, sol_file, checker)

    @staticmethod
    def validate_output_directly(submit_id, out_file, sol_file, floats_comparison):
        with open(out_file, "rt", encoding="cp866") as out:
            with open(sol_file, "rt", encoding="cp866") as sol:
                while True:
                    out_line = out.readline()
                    sol_line = sol.readline()
                    if not out_line and not sol_line:
                        return "", 1.0, ""

                    out_line = out_line.strip() if out_line else ""
                    sol_line = sol_line.strip() if sol_line else ""

                    if out_line == sol_line:
                        continue

                    # If a float (or a list of floats), try comparing with absolute or relative error
                    out_tokens = out_line.split()
                    sol_tokens = sol_line.split()

                    line_okay = True
                    if len(out_tokens) != len(sol_tokens):
                        line_okay = False
                    else:
                        for i in range(len(out_tokens)):
                            if out_tokens[i] == sol_tokens[i]:
                                continue
                            if not floats_comparison:
                                line_okay = False
                                break
                            else:
                                try:
                                    out_num = float(out_tokens[i])
                                    sol_num = float(sol_tokens[i])
                                    if fabs(out_num - sol_num) > config.FLOAT_PRECISION:
                                        abs_out_num, abs_sol_num = fabs(out_num), fabs(sol_num)
                                        if abs_out_num < (1.0 - config.FLOAT_PRECISION) * abs_sol_num or \
                                                abs_out_num > (1.0 + config.FLOAT_PRECISION) * abs_sol_num:
                                            line_okay = False
                                            break
                                except ValueError:
                                    logger.info("[Submission {}] Double parsing failed!".format(submit_id))
                                    line_okay = False
                                    break

                    if line_okay:
                        continue

                    # If none of the checks proved the answer to be correct, return a Wrong Answer
                    if len(out_line) > 20:
                        out_line = out_line[:17] + "..."
                    if len(sol_line) > 20:
                        sol_line = sol_line[:17] + "..."
                    return "Expected \"{}\" but received \"{}\".".format(sol_line, out_line), 0.0, ""

    @staticmethod
    def validate_output_with_checker(submit_id, inp_file, out_file, sol_file, checker):
        checker_binary_path = config.PATH_CHECKERS + checker + config.EXECUTABLE_EXTENSION_CPP
        process = subprocess.Popen(
            args=[checker_binary_path, inp_file, out_file, sol_file],
            executable=checker_binary_path,
            cwd=getcwd(),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE
        )
        try:
            exit_code = process.wait(timeout=config.CHECKER_TIMEOUT)
        except subprocess.TimeoutExpired:
            logger.error("[Submission {}] Internal Error: Checker took more than the allowed {}s.".format(
                submit_id, config.CHECKER_TIMEOUT))
            process.terminate()
            return "Checker Timeout", 0.0, ""

        if exit_code != 0:
            message = "Checker returned non-zero exit code. Error was: \"{error_message}\"" \
                .format(exit_code=exit_code, error_message=process.communicate()[1])
            return message, 0.0, ""

        output = process.communicate()
        result = output[0].decode("utf-8") if output[0] is not None else "0.0"
        info = output[1].decode("utf-8") if output[1] is not None else ""

        result_lines = result.splitlines()

        score = 0.0
        message = ""
        if len(result_lines) > 0:
            score = float(result_lines[0])
        if len(result_lines) > 1:
            message = result_lines[1] if result_lines[1] != "OK" else ""
        return message, score, info
