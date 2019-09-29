"""
Tests whether the runner is behaving as expected.
NOTE: Some of the tests in this file are heavily dependent on execution time
      and may give false positives or negatives if ran on different hardware.
"""

import json
import unittest
import shutil
import os
import warnings
from unittest import mock

import config
from status import TestStatus
from compiler import Compiler
from evaluator import Evaluator
from runner import Runner
from executor import Executor

PATH_SANDBOX = "tests/test_sandbox/"
PATH_DATA = "tests/test_data/"
PATH_TESTS = "tests/test_data/tests/"
PATH_FIXTURES = "tests/fixtures/"
PATH_THREESUM = "tests/fixtures/ThreeSum/"


class TestRunner(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        if not os.path.exists(PATH_SANDBOX):
            os.makedirs(PATH_SANDBOX)
        if not os.path.exists(PATH_DATA):
            os.makedirs(PATH_DATA)
        if not os.path.exists(PATH_TESTS):
            os.makedirs(PATH_TESTS)

        # Enable threading for the tests
        Compiler.COMPILE_LINE_CPP = Compiler.COMPILE_LINE_CPP.replace("g++", "g++ -pthread")

        # First, we need to create an Evaluator object
        cls.evaluator_cpp = cls.get_evaluator(os.path.join(PATH_FIXTURES, "tests_runner_cpp.json"))
        cls.evaluator_java = cls.get_evaluator(os.path.join(PATH_FIXTURES, "tests_runner_java.json"))

        # Then create the sandbox dir (same for all evaluators)
        cls.evaluator_cpp.create_sandbox_dir()

        # Then we need to compile the source
        Compiler.compile("C++", os.path.join(PATH_THREESUM, "ThreeSum.cpp"), cls.evaluator_cpp.path_executable)
        Compiler.compile("Java", os.path.join(PATH_THREESUM, "ThreeSum.java"), cls.evaluator_java.path_executable)

        # Then we need to copy the tests to the test_data folder
        for test in cls.evaluator_cpp.tests:
            shutil.copy(os.path.join(PATH_THREESUM, test["inpFile"]), os.path.join(PATH_TESTS, test["inpHash"]))
            shutil.copy(os.path.join(PATH_THREESUM, test["solFile"]), os.path.join(PATH_TESTS, test["solHash"]))

        config.PATH_TESTS = PATH_TESTS
        Executor.setup_containers(1)

    @staticmethod
    def get_evaluator(data_file):
        with open(data_file) as file:
            data = json.loads(file.read())
            return Evaluator(data)

    def setUp(self):
        warnings.simplefilter("ignore", ResourceWarning)

    """
    We'll use a dummy task for testing various run statuses. The task is the following:
    Given a number N, return the sum of products of all distinct triplets of numbers in [1, N] modulo 1000000007.
    """

    # Test ThreeSum_01: N = 20, the solution returns the correct answer (ACCEPTED)
    @mock.patch("updater.Updater.add_info")
    def test_accepted(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[1])
        self.assertIs(result.status, TestStatus.ACCEPTED)
        self.assertEqual(result.error_message, "")

    # Test ThreeSum_02: N = 200, the solution returns a wrong answer (WRONG_ANSWER)
    @mock.patch("updater.Updater.add_info")
    def test_wrong_answer(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[2])
        self.assertIs(result.status, TestStatus.WRONG_ANSWER)
        self.assertEqual("Expected", result.error_message[:8])
    
    # Test ThreeSum_03: N = 1700, the solution is slightly slow (TIME_LIMIT)
    @mock.patch("updater.Updater.add_info")
    def test_time_limit_close(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[3])
        self.assertIs(result.status, TestStatus.TIME_LIMIT)
        self.assertGreater(result.exec_time, self.evaluator_cpp.time_limit)

    # Test ThreeSum_04: N = 2200, the solution is slightly slow but catches the SIGTERM so it is killed
    @mock.patch("updater.Updater.add_info")
    def test_time_limit_close_handle_sigterm(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[4])
        self.assertIs(result.status, TestStatus.TIME_LIMIT)

    # Test ThreeSum_05: N = 3000, the solution is very slow (TIME_LIMIT)
    @mock.patch("updater.Updater.add_info")
    def test_time_limit_not_close(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[5])
        self.assertIs(result.status, TestStatus.TIME_LIMIT)
        self.assertGreater(result.exec_time, self.evaluator_cpp.time_limit)

    # Test ThreeSum_06: N = 20000, the solution accesses invalid array index (RUNTIME_ERROR)
    @mock.patch("updater.Updater.add_info")
    def test_runtime_error(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[6])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    # Test ThreeSum_07: N = 200000, the solution uses too much memory (MEMORY_LIMIT)
    @mock.patch("updater.Updater.add_info")
    def test_memory_limit(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[7])
        self.assertIs(result.status, TestStatus.MEMORY_LIMIT)
        self.assertGreater(result.exec_memory, self.evaluator_cpp.memory_limit)

    # Test ThreeSum_08: N = 13, the solution forks, but that's allowed (ACCEPTED)
    @mock.patch("updater.Updater.add_info")
    def test_forking_works(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[8])
        self.assertIs(result.status, TestStatus.ACCEPTED)
        self.assertEqual("", result.error_message)

    # Test ThreeSum_09: N = 17, the solution tries a fork bomb but cannot spawn more than few threads
    @mock.patch("updater.Updater.add_info")
    def test_forkbomb(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[9])
        self.assertIs(result.status, TestStatus.WRONG_ANSWER)
        self.assertEqual("Expected \"741285\" but received \"Cannot fork!\".", result.error_message)

    # Test ThreeSum_10: N = 1777, the solution uses several threads to calculate the answer
    # Make sure the combined time is reported. (TIME_LIMIT)
    @mock.patch("updater.Updater.add_info")
    def test_threading(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[10])
        self.assertIs(result.status, TestStatus.TIME_LIMIT)
        self.assertEqual("", result.error_message)

    # Test ThreeSum_11: N = 42, the solution is killed due to writing to file in current directory (RUNTIME_ERROR)
    @mock.patch("updater.Updater.add_info")
    def test_killed_writing_file_curr(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[11])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    # Test ThreeSum_12: N = 43, the solution is killed due to writing to file in home directory (RUNTIME_ERROR)
    @mock.patch("updater.Updater.add_info")
    def test_killed_writing_file_home(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[12])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    # Test ThreeSum_13: N = 665, the solution is printing a lot of output, but below the limit (WRONG_ANSWER)
    @mock.patch("updater.Updater.add_info")
    def test_near_output_limit(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[13])
        self.assertIs(result.status, TestStatus.WRONG_ANSWER)
        self.assertEqual(result.exit_code, 0)

    # Test ThreeSum_14: N = 666, the solution is killed due to exceeding output limit (RUNTIME_ERROR)
    @mock.patch("updater.Updater.add_info")
    def test_killed_output_limit_exceeded(self, _):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[14])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    @mock.patch("updater.Updater.add_info")
    def test_java_solution_run(self, _):
        # Run the solution
        result = Runner(self.evaluator_java).run_problem(-1, self.evaluator_java.tests[15])
        self.assertIs(result.status, TestStatus.ACCEPTED)
        self.assertEqual(result.error_message, "")

