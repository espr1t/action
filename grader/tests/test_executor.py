"""
Tests whether the runner is behaving as expected.
NOTE: Some of the tests in this file are heavily dependent on execution time
      and may give false positives or negatives if ran on different hardware.
"""

import shutil
import os
from unittest import TestCase, mock
from concurrent.futures import ThreadPoolExecutor
from time import perf_counter
from tempfile import NamedTemporaryFile

import config
from common import TestStatus
from compiler import Compiler
from executor import execute_problem
from runner import RunConfig
import initializer


class TestExecuteProblem(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/execute_problem/")

    # Do it this way instead of using a class decorator since otherwise the patching
    # is not active in the setUp() / tearDown() methods -- and we need it there as well
    patch_sandbox = mock.patch("config.PATH_SANDBOX", os.path.abspath("tests/test_sandbox/"))

    @classmethod
    def setUpClass(cls):
        initializer.init()

        cls.patch_sandbox.start()
        if not os.path.exists(config.PATH_SANDBOX):
            os.makedirs(config.PATH_SANDBOX)

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(config.PATH_SANDBOX)
        cls.patch_sandbox.stop()

    @classmethod
    def get_run_config(cls, evaluator):
        return RunConfig(
            time_limit=evaluator.time_limit,
            memory_limit=evaluator.memory_limit,
            executable_path=evaluator.path_executable,
            checker_path=evaluator.path_checker_executable,
            tester_path=evaluator.path_tester_executable,
            compare_floats=evaluator.floats
        )

    """
    def test_successful_execution_cpp(self, add_info):
        input_file = NamedTemporaryFile(mode="w+b", delete=False)
        input_file.write(b"133742")
        input_file.flush()
        expected_output = 42

        start_time = perf_counter()

        call_args = []
        add_info.side_effect = lambda result: call_args.append(result)

        evaluator = self.evaluator_cpp
        run_config = self.get_run_config(evaluator)

        test_futures = []
        result_id = 0
        pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_EXECUTORS)
        for test in evaluator.tests:
            future = pool.submit(execute_problem, evaluator.updater, evaluator.id, result_id, test, run_config)
            test_futures.append((test, future))
            result_id += 1

        for test, future in test_futures:
            try:
                future.result()  # Wait for the test to be executed
            except Exception as ex:
                self.fail("Got an exception: '{}'.".format(ex))
        total_time = perf_counter() - start_time
        print("Total time: {:.3f}s".format(total_time))
        print(call_args)
        self.assertTrue(total_time < evaluator.time_limit * len(evaluator.tests) / config.MAX_PARALLEL_EXECUTORS)
        self.assertEqual(add_info.call_count, len(evaluator.tests) * 2)
    """

    """
    def test_full_run_errors(self, add_info):
    """

    """
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