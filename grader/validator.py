"""
Validates whether a given output is valid or not.
This can happen in several different ways:
    >> Direct text comparison between the expected output and the user's output
    >> Comparison between floats (if the appropriate flag is set in the problem)
    >> Using a checker or a tester
"""

from re import finditer
from math import fabs
from dataclasses import dataclass

import config
from runner import RunConfig, RunResult
from common import TestStatus, TestInfo, get_logger

logger = get_logger(__file__)


@dataclass
class ValidatorResult:
    status: TestStatus
    score: float
    info: str = ""
    error: str = ""


class Validator:

    @staticmethod
    def determine_status(submit_id, test: TestInfo, run_config: RunConfig, run_result: RunResult) -> ValidatorResult:
        """
        Determines the final execution status (OK, WA, TL, ML, RE, or IE) and score of the solution
        """
        # TODO: We can be more specific what the RE actually is:
        # Killed (TL): Killed (exit_code = 9)
        # Killed (TL): Terminated (exit_code = 15)
        # Killed (RE, division by zero): Floating point exception (exit_code = 8)
        # Killed (RE, out of bounds): Segmentation fault (exit_code = 11)
        # Killed (RE, allocated too much memory): Segmentation fault (exit_code = 11)
        # Killed (RE, max output size exceeded): File size limit exceeded (exit_code = 25)

        # IE (Internal Error) - error message set previously
        if run_result.error != "":
            logger.error("Submit {id} | Got error while executing test {test_name}: \"{error}\"".format(
                id=submit_id, test_name=test.inpFile, error=run_result.error))
            return ValidatorResult(status=TestStatus.INTERNAL_ERROR, score=0.0, error=run_result.error)

        # ML (Memory Limit)
        if run_result.exec_memory > run_config.memory_limit:
            return ValidatorResult(status=TestStatus.MEMORY_LIMIT, score=0.0)

        # TL (Time Limit)
        if run_result.exec_time > run_config.time_limit:
            return ValidatorResult(status=TestStatus.TIME_LIMIT, score=0.0)

        # RE (Runtime Error)
        if run_result.exit_code != 0:
            return ValidatorResult(status=TestStatus.RUNTIME_ERROR, score=0.0)

        # AC (Accepted), WA (Wrong Answer), or IE (Internal Error)
        if run_config.checker_path is not None or run_config.tester_path is not None:
            return Validator.validate_output_from_checker_or_tester(submit_id, run_result.output)
        return Validator.validate_output_directly(test, run_result.output, run_config.compare_floats)

    @staticmethod
    def line_iterator(text):
        return (x.group(0) for x in finditer(r"[^\r\n]+", text))

    @staticmethod
    def token_iterator(text):
        return (x.group(0) for x in finditer(r"\S+", text))

    @staticmethod
    def floats_equal(num1, num2):
        # Absolute difference
        if fabs(num1 - num2) <= config.FLOAT_PRECISION:
            return True
        # Relative difference
        lower_bound = min((1.0 - config.FLOAT_PRECISION) * num2, (1.0 + config.FLOAT_PRECISION) * num2)
        upper_bound = max((1.0 - config.FLOAT_PRECISION) * num2, (1.0 + config.FLOAT_PRECISION) * num2)
        return lower_bound <= num1 <= upper_bound

    @staticmethod
    def validate_output_directly(test: TestInfo, output, compare_floats) -> ValidatorResult:
        expected_output_bytes = open(test.solPath, mode="rb").read()
        out_line_iterator = Validator.line_iterator(output.decode(encoding=config.OUTPUT_ENCODING))
        sol_line_iterator = Validator.line_iterator(expected_output_bytes.decode(encoding=config.OUTPUT_ENCODING))

        for sol_line in sol_line_iterator:
            out_line = next(out_line_iterator, "")
            if sol_line.strip() == out_line.strip():
                continue

            # Line is not exactly the same, but maybe the difference is acceptable
            # (e.g., if float comparison is enabled, numbers can have different precision or representation)
            out_token_iterator = Validator.token_iterator(out_line)
            sol_token_iterator = Validator.token_iterator(sol_line)

            for sol_token in sol_token_iterator:
                out_token = next(out_token_iterator, "")
                if out_token == sol_token:
                    continue
                try:
                    # If the tokens are floating-point numbers, try comparing with absolute or relative error
                    if compare_floats and Validator.floats_equal(float(sol_token), float(out_token)):
                        continue
                except ValueError:  # Apparently not floats...
                    pass

                # If none of the checks proved the answer to be correct, return Wrong Answer
                message = "Expected \"{}\" but got \"{}\".".format(
                    sol_line if len(sol_line) <= 20 else sol_line[:17] + "...",
                    out_line if len(out_line) <= 20 else out_line[:17] + "..."
                )
                return ValidatorResult(status=TestStatus.WRONG_ANSWER, score=0.0, info=message)

        # Although everything so far seems correct, maybe the contested printed extra output?
        for out_line in out_line_iterator:
            if not out_line.strip().isspace():
                message = "Output contained extra symbols."
                return ValidatorResult(status=TestStatus.WRONG_ANSWER, score=0.0, info=message)

        return ValidatorResult(status=TestStatus.ACCEPTED, score=1.0)

    @staticmethod
    def validate_output_from_checker_or_tester(submit_id, output: bytes) -> ValidatorResult:
        output_lines = output.decode(config.OUTPUT_ENCODING).splitlines()
        if len(output_lines) < 1:
            message = "Checker or tester's output didn't contain a score!"
            logger.error("[Submission {id}] Internal Error: {error}".format(id=submit_id, error=message))
            return ValidatorResult(status=TestStatus.INTERNAL_ERROR, score=0.0, error=message)

        try:
            score = float(output_lines[0].strip())
        except ValueError:
            message = "Checker or tester's score line didn't contain a number (value = '{}')!".format(output_lines[0])
            logger.error("[Submission {id}] Internal Error: {error}".format(id=submit_id, error=message))
            return ValidatorResult(status=TestStatus.INTERNAL_ERROR, score=0.0, error=message)

        message = "" if len(output_lines) < 2 else "\n".join([line.strip() for line in output_lines[1:]])
        if message != "" and not message.startswith("OK"):
            return ValidatorResult(status=TestStatus.WRONG_ANSWER, score=0.0, info=message)
        return ValidatorResult(status=TestStatus.ACCEPTED, score=score, info=message)
