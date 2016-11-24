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
        config.PATH_SANDBOX = "test_sandbox/"
        config.PATH_DATA = "test_data/"
        config.PATH_TESTS = "test_data/tests/"
        if not path.exists(config.PATH_SANDBOX):
            makedirs(config.PATH_SANDBOX)
        if not path.exists(config.PATH_DATA):
            makedirs(config.PATH_DATA)
        if not path.exists(config.PATH_TESTS):
            makedirs(config.PATH_TESTS)

    def tearDown(self):
        shutil.rmtree(config.PATH_SANDBOX)
        shutil.rmtree(config.PATH_DATA)

    def get_evaluator(self, data_file):
        with open(data_file) as file:
            data = json.loads(file.read())
            return Evaluator(data)

    """
    We'll use a dummy task for testing various run statuses. The task is the following:
    Given a number N, return the sum of products of all distinct triplets of numbers in [1, N] modulo 1000000007.
    """
    def test_run(self):
        # First, we need to create an Evaluator object
        evaluator = self.get_evaluator("fixtures/tests_runner.json")

        # Then create the sandbox dir
        evaluator.create_sandbox_dir()

        # Then we need to compile the source
        Compiler.compile("fixtures/ThreeSum/ThreeSum.cpp", "C++", evaluator.path_executable)

        # Then we need to copy the tests to the test_data folder
        for test in evaluator.tests:
            shutil.copy("fixtures/ThreeSum/" + test["inpFile"], config.PATH_TESTS + test["inpHash"])
            shutil.copy("fixtures/ThreeSum/" + test["solFile"], config.PATH_TESTS + test["solHash"])

        # Everything's set up now. We need to call the runner for each test and see whether we get the expected results

        # Test ThreeSum_01: N = 20, the solution returns the correct answer (ACCEPTED)
        status, message = Runner.run(evaluator, 0)
        self.assertEqual(status, TestStatus.ACCEPTED)
        self.assertEqual(message, "")

        # Test ThreeSum_02: N = 200, the solution returns a wrong answer (WRONG_ANSWER)
        status, message = Runner.run(evaluator, 1)
        self.assertEqual(status, TestStatus.WRONG_ANSWER)
        self.assertEqual(message[:8], "Expected")

        # Test ThreeSum_03: N = 2000, the solution is too slow (TIME_LIMIT)
        status, message = Runner.run(evaluator, 2)
        self.assertEqual(status, TestStatus.TIME_LIMIT)
        self.assertGreater(message, str(evaluator.time_limit))

        # Test ThreeSum_04: N = 20000, the solution accesses invalid array index (RUNTIME_ERROR)
        status, message = Runner.run(evaluator, 3)
        self.assertEqual(status, TestStatus.RUNTIME_ERROR)

        # Test ThreeSum_05: N = 200000, the solution uses too much memory (MEMORY_LIMIT)
        status, message = Runner.run(evaluator, 4)
        self.assertEqual(status, TestStatus.MEMORY_LIMIT)


