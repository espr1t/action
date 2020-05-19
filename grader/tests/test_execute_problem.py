"""
Tests whether the runner is behaving as expected.
NOTE: Some of the tests in this file are heavily dependent on execution time
      and may give false positives or negatives if ran on different hardware.
"""

"""
import shutil
import os
import warnings
from unittest import TestCase, mock

import config
from common import TestStatus
from compiler import Compiler
from execute_problem import execute_problem
import runner
from sandbox import Sandbox
import initializer
from tests.helper import get_evaluator


class TestExecuteProblem(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/runner/")

    # Do it this way instead of using a class decorator since otherwise the patching
    # is not active in the setUp() / tearDown() methods -- and we need it there as well
    patch_tests = mock.patch("config.PATH_TESTS", os.path.abspath("tests/test_data/"))
    patch_sandbox = mock.patch("config.PATH_SANDBOX", os.path.abspath("tests/test_sandbox/"))
    patch_add_info = mock.patch("updater.Updater.add_info")

    @classmethod
    def setUpClass(cls):
        initializer.init()

        cls.patch_tests.start()
        cls.patch_sandbox.start()
        cls.patch_add_info.start()

        # TODO: Do we need this?
        # warnings.simplefilter("ignore", ResourceWarning)

        if not os.path.exists(config.PATH_SANDBOX):
            os.makedirs(config.PATH_SANDBOX)
        if not os.path.exists(config.PATH_TESTS):
            os.makedirs(config.PATH_TESTS)

        # First, we need to create an Evaluator object
        cls.evaluator_cpp = get_evaluator(os.path.join(cls.PATH_FIXTURES, "tests_runner_cpp.json"))
        cls.evaluator_java = get_evaluator(os.path.join(cls.PATH_FIXTURES, "tests_runner_java.json"))
        cls.evaluator_python = get_evaluator(os.path.join(cls.PATH_FIXTURES, "tests_runner_python.json"))

        # Then create the submit file storage dir (same for all evaluators)
        cls.evaluator_cpp.create_sandbox_dir()

        # Then we need to compile the sources
        Compiler.compile(config.LANGUAGE_CPP, os.path.join(cls.PATH_FIXTURES, "ThreeSum/Solutions/ThreeSum.cpp"),
                         cls.evaluator_cpp.path_executable)
        Compiler.compile(config.LANGUAGE_JAVA, os.path.join(cls.PATH_FIXTURES, "ThreeSum/Solutions/ThreeSum.java"),
                         cls.evaluator_java.path_executable)
        Compiler.compile(config.LANGUAGE_PYTHON, os.path.join(cls.PATH_FIXTURES, "ThreeSum/Solutions/ThreeSum.py"),
                         cls.evaluator_python.path_executable)

        # Then we need to copy the tests to the test_data folder
        for test in cls.evaluator_cpp.tests:
            shutil.copy(os.path.join(cls.PATH_FIXTURES, "ThreeSum/Tests", test.inpFile), test.inpPath)
            shutil.copy(os.path.join(cls.PATH_FIXTURES, "ThreeSum/Tests", test.solFile), test.solPath)

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(config.PATH_SANDBOX)
        shutil.rmtree(config.PATH_TESTS)

        cls.patch_tests.stop()
        cls.patch_sandbox.stop()
        cls.patch_add_info.stop()

    # We'll use a dummy task for testing various run statuses. The task is the following:
    # Given a number N, return the sum of products of all distinct triplets of numbers in [1, N] modulo 1000000007.

    # Test ThreeSum_01: N = 20, the solution returns the correct answer (ACCEPTED)
    def test_accepted(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[1])
        self.assertIs(result.status, TestStatus.ACCEPTED)
        self.assertEqual(result.error_message, "")

    # Test ThreeSum_02: N = 200, the solution returns a wrong answer (WRONG_ANSWER)
    def test_wrong_answer(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[2])
        self.assertIs(result.status, TestStatus.WRONG_ANSWER)
        self.assertEqual("Expected", result.error_message[:8])

    # Test ThreeSum_03: N = 1700, the solution is slightly slow (TIME_LIMIT)
    def test_time_limit_close(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[3])
        self.assertIs(result.status, TestStatus.TIME_LIMIT)
        self.assertGreater(result.exec_time, self.evaluator_cpp.time_limit)

    # Test ThreeSum_04: N = 2200, the solution is slightly slow but catches the SIGTERM so it is killed
    def test_time_limit_close_handle_sigterm(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[4])
        self.assertIs(result.status, TestStatus.TIME_LIMIT)

    # Test ThreeSum_05: N = 3000, the solution is very slow (TIME_LIMIT)
    def test_time_limit_not_close(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[5])
        self.assertIs(result.status, TestStatus.TIME_LIMIT)
        self.assertGreater(result.exec_time, self.evaluator_cpp.time_limit)

    # Test ThreeSum_06: N = 20000, the solution accesses invalid array index (RUNTIME_ERROR)
    def test_runtime_error(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[6])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    # Test ThreeSum_07: N = 200000, the solution uses too much memory (MEMORY_LIMIT)
    def test_memory_limit(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[7])
        self.assertIs(result.status, TestStatus.MEMORY_LIMIT)
        self.assertGreater(result.exec_memory, self.evaluator_cpp.memory_limit)

    # Test ThreeSum_08: N = 13, the solution forks, but that's allowed (ACCEPTED)
    def test_forking_works(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[8])
        self.assertIs(result.status, TestStatus.ACCEPTED)
        self.assertEqual("", result.error_message)

    # Test ThreeSum_09: N = 17, the solution tries a fork bomb but cannot spawn more than few threads
    def test_forkbomb(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[9])
        self.assertIs(result.status, TestStatus.WRONG_ANSWER)
        self.assertEqual("Expected \"741285\" but received \"Cannot fork!\".", result.error_message)

    # Test ThreeSum_10: N = 1777, the solution uses several threads to calculate the answer
    # Make sure the combined time is reported. (TIME_LIMIT)
    @mock.patch("Compiler.COMPILE_COMMAND_CPP", Compiler.COMPILE_COMMAND_CPP.replace("g++", "g++ -pthread"))
    def test_threading(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[10])
        self.assertIs(result.status, TestStatus.TIME_LIMIT)
        self.assertEqual("", result.error_message)

    # Test ThreeSum_11: N = 42, the solution is killed due to writing to file in current directory (RUNTIME_ERROR)
    def test_killed_writing_file_curr(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[11])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    # Test ThreeSum_12: N = 43, the solution is killed due to writing to file in home directory (RUNTIME_ERROR)
    def test_killed_writing_file_home(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[12])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    # Test ThreeSum_13: N = 665, the solution is printing a lot of output, but below the limit (WRONG_ANSWER)
    def test_near_output_limit(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[13])
        self.assertIs(result.status, TestStatus.WRONG_ANSWER)
        self.assertEqual(result.exit_code, 0)

    # Test ThreeSum_14: N = 666, the solution is killed due to exceeding output limit (RUNTIME_ERROR)
    def test_killed_output_limit_exceeded(self):
        result = Runner(self.evaluator_cpp).run_problem(-1, self.evaluator_cpp.tests[14])
        self.assertIs(result.status, TestStatus.RUNTIME_ERROR)
        self.assertNotEqual(result.exit_code, 0)

    def test_java_solution_run(self):
        # Run the solution
        result = Runner(self.evaluator_java).run_problem(-1, self.evaluator_java.tests[15])
        self.assertIs(result.status, TestStatus.ACCEPTED)
        self.assertEqual(result.error_message, "")
"""
