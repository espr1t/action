"""
Tests whether the runner is behaving as expected.
NOTE: Some of the tests in this file are heavily dependent on execution time
      and may give false positives or negatives if ran on different hardware.
"""
import pytest
import shutil
import os
from unittest import TestCase, mock
from time import perf_counter

from updater import Updater
import config
import common
from common import TestStatus, TestInfo
from compiler import Compiler
from executor import execute_problem
from runner import RunConfig
import initializer


class TestExecuteProblem(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/executor/")

    # Do it this way instead of using a class decorator since otherwise the patching
    # is not active in the setUp() / tearDown() methods -- and we need it there as well
    patch_sandbox = mock.patch("config.PATH_SANDBOX", os.path.abspath("tests/fake_sandbox/"))

    @classmethod
    def setUpClass(cls):
        initializer.init()
        cls.patch_sandbox.start()
        os.makedirs(config.PATH_SANDBOX, exist_ok=True)

        cls.tests_abproblem = []
        for pos in range(3):
            test = TestInfo(inpFile="test.in", inpHash="hash.in", solFile="test.sol", solHash="hash.sol", position=pos)
            test.inpPath = os.path.join(cls.PATH_FIXTURES, "ABProblem/Tests/ABProblem.{:02d}.in".format(pos))
            test.solPath = os.path.join(cls.PATH_FIXTURES, "ABProblem/Tests/ABProblem.{:02d}.sol".format(pos))
            cls.tests_abproblem.append(test)

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(config.PATH_SANDBOX)
        cls.patch_sandbox.stop()

    @mock.patch("updater.Updater.add_info")
    def exec_helper(self, add_info, path_source, tests, run_config, expected_results):
        updater = Updater("fake/endpoint", 42, [])
        updater_results = []
        add_info.side_effect = lambda result: updater_results.append(result)

        # Configure fake paths to the solution and its executable and compile it
        language = common.get_language_by_source_name(path_source)
        path_executable = os.path.join(config.PATH_SANDBOX, "solution.{}".format(common.get_executable_extension(language)))
        compilation_status = Compiler.compile(
            language=language,
            path_source=path_source,
            path_executable=path_executable
        )
        self.assertEqual(compilation_status, "")
        run_config.executable_path = path_executable

        try:
            for test in tests:
                execute_problem(updater=updater, submit_id=42, result_id=0, test=test, run_config=run_config)
        except:
            self.fail("Failed during execution of tests.")

        self.assertEqual(add_info.call_count, len(tests) * 2)
        for result in updater_results:
            if result["status"] != TestStatus.TESTING.name:
                # print(result)
                found = False
                for i in range(len(expected_results)):
                    if result["status"] == expected_results[i].name:
                        found = True
                        del expected_results[i]
                        break
                self.assertTrue(found, msg="Status '{}' not among expected results.".format(result["status"]))

    @pytest.mark.order(5000)
    def test_successful_execution_cpp(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblem.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.ACCEPTED, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5001)
    def test_successful_execution_cpp2(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblem2.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.ACCEPTED, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5003)
    def test_unsuccessful_execution_cpp(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemWA1.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.WRONG_ANSWER, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5004)
    def test_successful_execution_java(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblem.java"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.ACCEPTED, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5005)
    def test_successful_execution_python(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblem.py"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.ACCEPTED, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5006)
    def test_floating_point_comparison(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=False)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblem.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.WRONG_ANSWER, TestStatus.WRONG_ANSWER, TestStatus.WRONG_ANSWER]
        )

    @pytest.mark.order(5007)
    def test_time_limit(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemTL.py"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.TIME_LIMIT, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5008)
    def test_printing_extra_output(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemWA2.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.WRONG_ANSWER, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5009)
    def test_writing_to_file(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemRE1.py"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.RUNTIME_ERROR, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5010)
    def test_exceeding_output_limit(self):
        run_config = RunConfig(time_limit=3.0, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemRE2.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.RUNTIME_ERROR, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5011)
    def test_forking(self):
        run_config = RunConfig(time_limit=0.2, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemFork.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.WRONG_ANSWER, TestStatus.WRONG_ANSWER, TestStatus.WRONG_ANSWER]
        )

    @pytest.mark.order(5012)
    def test_spawning_fork_bomb(self):
        run_config = RunConfig(time_limit=1.0, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemFB.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.WRONG_ANSWER, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5013)
    def test_counting_total_time_of_all_threads(self):
        run_config = RunConfig(time_limit=0.5, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemThreads.java"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.TIME_LIMIT, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5014)
    def test_counting_total_time_of_all_processes(self):
        run_config = RunConfig(time_limit=0.5, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemTime.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.TIME_LIMIT, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5015)
    def test_segfaults_10_elements(self):
        run_config = RunConfig(time_limit=0.5, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemSeg1.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.ACCEPTED, TestStatus.RUNTIME_ERROR]
        )

    @pytest.mark.order(5016)
    def test_segfaults_4K_elements(self):
        run_config = RunConfig(time_limit=0.5, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemSeg2.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.ACCEPTED, TestStatus.RUNTIME_ERROR]
        )

    @pytest.mark.order(5017)
    def test_segfaults_4M_elements(self):
        run_config = RunConfig(time_limit=0.5, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemSeg3.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.ACCEPTED, TestStatus.RUNTIME_ERROR]
        )

    @pytest.mark.order(5018)
    def test_exec_from_program(self):
        run_config = RunConfig(time_limit=0.5, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemExec.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            # Currently there is no limitation to exec with system() and possibly others =(
            expected_results=[TestStatus.ACCEPTED, TestStatus.ACCEPTED, TestStatus.ACCEPTED]
        )

    @pytest.mark.order(5019)
    def test_out_of_memory(self):
        run_config = RunConfig(time_limit=3.0, memory_limit=67108864, executable_path="fake/path", compare_floats=True)
        self.exec_helper(
            path_source=os.path.join(self.PATH_FIXTURES, "ABProblem/Solutions/ABProblemOOM.cpp"),
            tests=self.tests_abproblem,
            run_config=run_config,
            expected_results=[TestStatus.ACCEPTED, TestStatus.MEMORY_LIMIT, TestStatus.ACCEPTED]
        )
