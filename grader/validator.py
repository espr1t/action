"""
Validates whether a given output is valid or not.
This can happen in several different ways:
    >> Direct text comparison between the expected output and the user's output
    >> Comparison between floats (if we detect they are floats and the flag is set in the problem)
    >> Using a checker
"""

import logging
import psutil
from subprocess import PIPE
from os import getcwd
from math import fabs
import config


class Validator:

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
                                    logger = logging.getLogger("vldtr")
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
        process = psutil.Popen(args=[checker_binary_path, inp_file, out_file, sol_file],
                               executable=checker_binary_path, cwd=getcwd(), stdout=PIPE, stderr=PIPE)
        try:
            exit_code = process.wait(timeout=config.CHECKER_TIMEOUT)
        except psutil.TimeoutExpired:
            logger = logging.getLogger("vldtr")
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
