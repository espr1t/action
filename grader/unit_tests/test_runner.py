"""
Tests whether the evaluator is behaving as expected.
"""

import json
import unittest
import shutil
from os import path, makedirs

import config
from status import TestStatus
from compiler import Compiler
from evaluator import Evaluator
from runner import Runner


class TestRunner(unittest.TestCase):
    def setUp(self):
        config.PATH_SANDBOX = "unit_tests/test_sandbox/"
        config.PATH_DATA = "unit_tests/test_data/"
        config.PATH_TESTS = "unit_tests/test_data/tests/"
        if not path.exists(config.PATH_SANDBOX):
            makedirs(config.PATH_SANDBOX)
        if not path.exists(config.PATH_DATA):
            makedirs(config.PATH_DATA)
        if not path.exists(config.PATH_TESTS):
            makedirs(config.PATH_TESTS)

        # First, we need to create an Evaluator object
        self.evaluator = self.get_evaluator("unit_tests/fixtures/tests_runner.json")

        # Then create the sandbox dir
        self.evaluator.create_sandbox_dir()

        # Then we need to compile the source
        Compiler.compile("C++", "unit_tests/fixtures/ThreeSum/ThreeSum.cpp", self.evaluator.path_executable)

        # Then we need to copy the tests to the test_data folder
        for test in self.evaluator.tests:
            shutil.copy("unit_tests/fixtures/ThreeSum/" + test["inpFile"], config.PATH_TESTS + test["inpHash"])
            shutil.copy("unit_tests/fixtures/ThreeSum/" + test["solFile"], config.PATH_TESTS + test["solHash"])

    def get_evaluator(self, data_file):
        with open(data_file) as file:
            data = json.loads(file.read())
            return Evaluator(data)

    """
    We'll use a dummy task for testing various run statuses. The task is the following:
    Given a number N, return the sum of products of all distinct triplets of numbers in [1, N] modulo 1000000007.
    """

    # Test ThreeSum_01: N = 20, the solution returns the correct answer (ACCEPTED)
    def test_accepted(self):
        result = Runner(self.evaluator).run(self.evaluator.tests[0])
        self.assertIs(result.status, TestStatus.ACCEPTED)
        self.assertEqual(result.error_message, "")

    # Test ThreeSum_02: N = 200, the solution returns a wrong answer (WRONG_ANSWER)
    def test_wrong_answer(self):
        result = Runner(self.evaluator).run(self.evaluator.tests[1])
        self.assertIs(result.status, TestStatus.WRONG_ANSWER)
        self.assertEqual("Expected", result.error_message[:8])

    # Test ThreeSum_03: N = 2000, the solution is too slow (TIME_LIMIT)
    def test_time_limit(self):
        result = Runner(self.evaluator).run(self.evaluator.tests[2])
        self.assertIs(result.status, TestStatus.TIME_LIMIT)
        self.assertGreater(result.exec_time, self.evaluator.time_limit)

    # Test ThreeSum_04: N = 20000, the solution accesses invalid array index (RUNTIME_ERROR)
    def test_runtime_error(self):
        result = Runner(self.evaluator).run(self.evaluator.tests[3])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    # Test ThreeSum_05: N = 200000, the solution uses too much memory (MEMORY_LIMIT)
    def test_memory_limit(self):
        result = Runner(self.evaluator).run(self.evaluator.tests[4])
        self.assertIs(result.status, TestStatus.MEMORY_LIMIT)
        self.assertGreater(result.exec_memory, self.evaluator.memory_limit)

    # Test ThreeSum_06: N = 13, the solution is killed due to an attempt to fork() (RUNTIME_ERROR)
    def test_killed_forking(self):
        result = Runner(self.evaluator).run(self.evaluator.tests[5])
        self.assertIs(result.status, TestStatus.WRONG_ANSWER)
        self.assertEqual("Expected \"165620\" but received \"Cannot fork!\".", result.error_message)

    # Test ThreeSum_07: N = 42, the solution is killed due to writing to file in current directory (RUNTIME_ERROR)
    def test_killed_writing_file_curr(self):
        result = Runner(self.evaluator).run(self.evaluator.tests[6])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    """
    # Test ThreeSum_08: N = 43, the solution is killed due to writing to file in home directory (RUNTIME_ERROR)
    def test_killed_writing_file_home(self):
        result = Runner(self.evaluator).run(self.evaluator.tests[7])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)
    """

    # Test ThreeSum_09: N = 666, the solution is killed due to exceeding output limit (RUNTIME_ERROR)
    def test_killed_output_limit_exceeded(self):
        result = Runner(self.evaluator).run(self.evaluator.tests[8])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    def test_absolute_or_relative_comparison(self):
        runner = Runner(self.evaluator)

        # Absolute difference is less than 10-9
        message, score = runner.validate_output('unit_tests/fixtures/FloatComparison/FloatAbsoluteOK.out',
                                                'unit_tests/fixtures/FloatComparison/FloatAbsolute.sol')
        self.assertEqual('', message)
        self.assertEqual(1.0, score)

        # Absolute difference is greater than 10-9
        message, score = runner.validate_output('unit_tests/fixtures/FloatComparison/FloatAbsoluteWA.out',
                                                'unit_tests/fixtures/FloatComparison/FloatAbsolute.sol')
        self.assertNotEqual('', message)
        self.assertEqual(0.0, score)

        # Relative difference is less than 10-9
        message, score = runner.validate_output('unit_tests/fixtures/FloatComparison/FloatRelativeOK.out',
                                                'unit_tests/fixtures/FloatComparison/FloatRelative.sol')
        self.assertEqual('', message)
        self.assertEqual(1.0, score)

        # Relative difference is greater than 10-9
        message, score = runner.validate_output('unit_tests/fixtures/FloatComparison/FloatRelativeWA.out',
                                                'unit_tests/fixtures/FloatComparison/FloatRelative.sol')
        self.assertNotEqual('', message)
        self.assertEqual(0.0, score)

    def test_presentation_difference_comparison(self):
        runner = Runner(self.evaluator)

        # Trailing spaces at the end of the lines or after the last line are okay
        message, score = runner.validate_output('unit_tests/fixtures/TextComparison/TextComparisonPE.out',
                                                'unit_tests/fixtures/TextComparison/TextComparisonOK.sol')
        self.assertEqual('', message)
        self.assertEqual(1.0, score)
