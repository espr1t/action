"""
Tests whether the evaluator is behaving as expected.
"""
import os
import shutil
import vcr
import logging
import json
from unittest import TestCase, mock

import config
import common
from common import TestStatus
from compiler import Compiler
from evaluator import Evaluator
import initializer


class TestEvaluator(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/evaluator")

    # Do it this way instead of using a class decorator since otherwise the patching
    # is not active in the setUp() / tearDown() methods -- and we need it there as well
    patch_sandbox = mock.patch("config.PATH_SANDBOX", os.path.abspath("tests/fake_sandbox/"))
    patch_tests = mock.patch("config.PATH_TESTS", os.path.abspath("tests/fake_tests/"))
    patch_testers = mock.patch("config.PATH_TESTERS", os.path.abspath("tests/fake_testers/"))
    patch_checkers = mock.patch("config.PATH_CHECKERS", os.path.abspath("tests/fake_checkers/"))
    patch_replays = mock.patch("config.PATH_REPLAYS", os.path.abspath("tests/fake_replays/"))

    @classmethod
    def setUpClass(cls):
        initializer.init()
        logging.getLogger("vcr").setLevel(logging.FATAL)

        # Start mocks and create fake directories
        cls.patch_sandbox.start()
        os.makedirs(config.PATH_SANDBOX, exist_ok=True)
        cls.patch_tests.start()
        os.makedirs(config.PATH_TESTS, exist_ok=True)
        cls.patch_testers.start()
        os.makedirs(config.PATH_TESTERS, exist_ok=True)
        cls.patch_checkers.start()
        os.makedirs(config.PATH_CHECKERS, exist_ok=True)
        cls.patch_replays.start()
        os.makedirs(config.PATH_REPLAYS, exist_ok=True)

        tasks = [
            ("problems_standard_three.json", "ThreeSum"),
            ("problems_standard_sheep.json", "Sheep"),
            ("problems_checker_ruler.json", "Ruler"),
            ("games_two_player_uttt.json", "UTTT"),
            ("games_interactive_ng.json", "NG"),
            ("games_interactive_is.json", "ImageScanner")
        ]

        # Create an Evaluator objects for each task and copy its tests in the test_data folder
        for task in tasks:
            evaluator = cls.get_evaluator(os.path.join(cls.PATH_FIXTURES, task[0]), config.LANGUAGE_CPP)
            for test in evaluator.tests:
                shutil.copy(os.path.join(cls.PATH_FIXTURES, "{}/Tests".format(task[1]), test.inpFile), test.inpPath)
                shutil.copy(os.path.join(cls.PATH_FIXTURES, "{}/Tests".format(task[1]), test.solFile), test.solPath)

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(config.PATH_SANDBOX)
        cls.patch_sandbox.stop()
        shutil.rmtree(config.PATH_TESTS)
        cls.patch_tests.stop()
        shutil.rmtree(config.PATH_TESTERS)
        cls.patch_testers.stop()
        shutil.rmtree(config.PATH_CHECKERS)
        cls.patch_checkers.stop()
        shutil.rmtree(config.PATH_REPLAYS)
        cls.patch_replays.stop()

    @classmethod
    def get_evaluator(cls, data_file, language) -> Evaluator:
        with open(os.path.join(cls.PATH_FIXTURES, data_file)) as file:
            data = json.loads(file.read())
            data["language"] = language
            return Evaluator(data)

    def test_create_sandbox_dir(self):
        evaluator = self.get_evaluator("problem_submit_ok.json", config.LANGUAGE_CPP)
        self.assertFalse(os.path.exists(evaluator.path_sandbox))
        evaluator.create_sandbox_dir()
        self.assertTrue(os.path.exists(evaluator.path_sandbox))

    @vcr.use_cassette("tests/fixtures/cassettes/download_tests.yaml")
    def test_download_tests(self):
        evaluator = self.get_evaluator("problem_submit_ok.json", config.LANGUAGE_CPP)

        # Assert none of the files is already present
        for test in evaluator.tests:
            self.assertFalse(os.path.exists(test.inpPath))
            self.assertFalse(os.path.exists(test.solPath))

        # Do the actual download
        evaluator.download_tests()

        # Assert all of the files are now present
        for test in evaluator.tests:
            self.assertTrue(os.path.exists(test.inpPath))
            self.assertTrue(os.path.exists(test.solPath))

    def test_write_source(self):
        evaluator = self.get_evaluator("problem_submit_ok.json", config.LANGUAGE_CPP)
        self.assertFalse(os.path.isfile(evaluator.path_source))
        evaluator.create_sandbox_dir()
        evaluator.write_source(evaluator.source, evaluator.path_source)
        self.assertTrue(os.path.isfile(evaluator.path_source))
        with open(evaluator.path_source, "rt") as file:
            self.assertEqual(evaluator.source, file.read())

    def test_cleanup(self):
        # Create a new instance and write the source
        evaluator = self.get_evaluator("problem_submit_ok.json", config.LANGUAGE_CPP)
        evaluator.create_sandbox_dir()
        evaluator.write_source(evaluator.source, evaluator.path_source)

        # Assert the submit directory and source file are created
        self.assertTrue(os.path.exists(evaluator.path_sandbox))
        self.assertTrue(os.path.isfile(evaluator.path_source))

        # Do the cleanup
        evaluator.cleanup()

        # Assert the submit directory and source file are removed
        self.assertFalse(os.path.isfile(evaluator.path_source))
        self.assertFalse(os.path.exists(evaluator.path_sandbox))

    @mock.patch("updater.Updater.add_info")
    def standard_problem_helper(self, add_info, evaluator, language, source, time_lb, memory_lb, expected_errors):
        evaluator.create_sandbox_dir()

        compilation_status = Compiler.compile(
            language=language,
            path_source=source,
            path_executable=evaluator.path_executable
        )
        self.assertEqual(compilation_status, "")

        updater_results = []
        add_info.side_effect = lambda result: updater_results.append(result)

        self.assertTrue(evaluator.run_solution())
        self.assertEqual(add_info.call_count, len(evaluator.tests) * 2)

        actual_non_ok = {}
        max_time, max_memory = -1e100, -1e100
        for res in updater_results:
            if res["status"] != TestStatus.TESTING.name:
                # print(res)
                if res["status"] != TestStatus.ACCEPTED.name:
                    # This is only because we only test wrong answers from the task with checkers
                    # In real tasks this is usually empty
                    if res["status"] == TestStatus.WRONG_ANSWER.name:
                        self.assertNotEqual(res["info"], "")

                    if res["status"] not in actual_non_ok:
                        actual_non_ok[res["status"]] = 1
                    else:
                        actual_non_ok[res["status"]] += 1

                max_time = max(max_time, res["exec_time"])
                max_memory = max(max_memory, res["exec_memory"])

        time_upper_bound = evaluator.time_limit * 3 + max(0.2, evaluator.time_limit * 0.2)
        self.assertGreaterEqual(max_time, time_lb)
        self.assertLessEqual(max_time, time_upper_bound)
        self.assertGreaterEqual(max_memory, memory_lb)
        self.assertLessEqual(max_memory, evaluator.memory_limit)

        for key in actual_non_ok.keys():
            if key not in expected_errors:
                self.fail("Got status {} which was not expected.".format(key))
            if actual_non_ok[key] != expected_errors[key]:
                self.fail("Expected {} results with status {} but got {}.".format(
                    expected_errors[key], key, actual_non_ok[key]))
        for key in expected_errors.keys():
            if key not in actual_non_ok:
                self.fail("Expected status {} but didn't receive it.".format(key))

    ########################################################
    #                      Three Sum                       #
    #                   (sample problem)                   #
    ########################################################
    def test_standard_problem_three_sum_cpp(self):
        evaluator = self.get_evaluator("problems_standard_three.json", config.LANGUAGE_CPP)
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "ThreeSum/Solutions/ThreeSum.cpp"),
            time_lb=0.0,
            memory_lb=1.0,
            expected_errors={}
        )

    def test_standard_problem_three_sum_java(self):
        evaluator = self.get_evaluator("problems_standard_three.json", config.LANGUAGE_JAVA)
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_JAVA,
            source=os.path.join(self.PATH_FIXTURES, "ThreeSum/Solutions/ThreeSum.java"),
            time_lb=0.0,
            memory_lb=0.0,
            expected_errors={}
        )

    def test_standard_problem_three_sum_python(self):
        evaluator = self.get_evaluator("problems_standard_three.json", config.LANGUAGE_PYTHON)
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_PYTHON,
            source=os.path.join(self.PATH_FIXTURES, "ThreeSum/Solutions/ThreeSum.py"),
            time_lb=0.1,
            memory_lb=1.0,
            expected_errors={}
        )

    ########################################################
    #                        Sheep                         #
    #             (real problem, many tests)               #
    ########################################################
    def test_standard_problem_sheep_cpp(self):
        evaluator = self.get_evaluator("problems_standard_sheep.json", config.LANGUAGE_CPP)
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "Sheep/Solutions/Sheep.cpp"),
            time_lb=0.1,
            memory_lb=1.0,
            expected_errors={}
        )

    def test_standard_problem_sheep_java(self):
        evaluator = self.get_evaluator("problems_standard_sheep.json", config.LANGUAGE_JAVA)
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_JAVA,
            source=os.path.join(self.PATH_FIXTURES, "Sheep/Solutions/Sheep.java"),
            time_lb=0.2,
            memory_lb=1.0,
            expected_errors={}
        )

    ########################################################
    #                        Ruler                         #
    #                    (has checker)                     #
    ########################################################
    def test_task_with_checker(self):
        evaluator = self.get_evaluator("problems_checker_ruler.json", config.LANGUAGE_CPP)

        # Configure fake paths to the checker and its executable and compile it
        evaluator.path_checker_source = os.path.join(self.PATH_FIXTURES, "Ruler/Checker/RulerChecker.cpp")
        evaluator.path_checker_executable = os.path.join(config.PATH_CHECKERS, "RulerChecker.o")
        compilation_status = Compiler.compile(
            language=config.LANGUAGE_CPP,
            path_source=evaluator.path_checker_source,
            path_executable=evaluator.path_checker_executable
        )
        self.assertEqual(compilation_status, "")

        # AC
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "Ruler/Solutions/Ruler.cpp"),
            time_lb=0.0,
            memory_lb=0.0,
            expected_errors={}
        )

        # TL
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "Ruler/Solutions/RulerTL.cpp"),
            time_lb=0.2,
            memory_lb=1.0,
            expected_errors={"TIME_LIMIT": 7}
        )

        # WA
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "Ruler/Solutions/RulerWA.cpp"),
            time_lb=0.0,
            memory_lb=0.0,
            expected_errors={"WRONG_ANSWER": 4}
        )

    ########################################################
    #                   Number Guessing                    #
    #                  (interactive game)                  #
    ########################################################
    def test_interactive_game_ng(self):
        evaluator = self.get_evaluator("games_interactive_ng.json", config.LANGUAGE_CPP)

        # Configure fake paths to the tester and its executable and compile it
        evaluator.path_tester_source = os.path.join(self.PATH_FIXTURES, "NG/Tester/NumberGuessingTester.cpp")
        evaluator.path_tester_executable = os.path.join(config.PATH_TESTERS, "NumberGuessingTester.o")

        # Compile the tester
        compilation_status = Compiler.compile(
            language=config.LANGUAGE_CPP,
            path_source=evaluator.path_tester_source,
            path_executable=evaluator.path_tester_executable
        )
        self.assertEqual(compilation_status, "")

        # OK
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "NG/Solutions/NumberGuessing.cpp"),
            time_lb=0.0,
            memory_lb=0.0,
            expected_errors={}
        )

        # TL
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "NG/Solutions/NumberGuessingTL.cpp"),
            time_lb=0.0,
            memory_lb=0.0,
            expected_errors={"TIME_LIMIT": 4}
        )

        # WA
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "NG/Solutions/NumberGuessingWA.cpp"),
            time_lb=0.0,
            memory_lb=0.0,
            expected_errors={"WRONG_ANSWER": 7}
        )

    ########################################################
    #                    Image Scanner                     #
    #                  (interactive game)                  #
    ########################################################
    def test_interactive_game_is(self):
        # The only difference with the previous is that this problem has much larger input and output
        # and the tester has to print lots of information in the end (thus, may take its time to finish)
        evaluator = self.get_evaluator("games_interactive_is.json", config.LANGUAGE_CPP)

        # Configure fake paths to the tester and its executable and compile it
        evaluator.path_tester_source = os.path.join(self.PATH_FIXTURES, "ImageScanner/Tester/ImageScannerTester.cpp")
        evaluator.path_tester_executable = os.path.join(config.PATH_TESTERS, "ImageScannerTester.o")

        # Compile the tester
        compilation_status = Compiler.compile(
            language=config.LANGUAGE_CPP,
            path_source=evaluator.path_tester_source,
            path_executable=evaluator.path_tester_executable
        )
        self.assertEqual(compilation_status, "")

        # OK
        self.standard_problem_helper(
            evaluator=evaluator,
            language=config.LANGUAGE_CPP,
            source=os.path.join(self.PATH_FIXTURES, "ImageScanner/Solutions/ImageScanner.cpp"),
            time_lb=0.0,
            memory_lb=0.0,
            expected_errors={}
        )

    ########################################################
    #                 Ultimate Tic-Tac-Toe                 #
    #                   (two-player game)                  #
    ########################################################
    @mock.patch("updater.Updater.add_info")
    def game_helper(self, add_info, evaluator, expected_messages):
        # We expect two updates per test per match (forward and reverse games)
        self.assertEqual(len(expected_messages), 2 * len(evaluator.tests) * len(evaluator.matches))

        updater_results = []
        add_info.side_effect = lambda result: updater_results.append(result)

        # Run the match(es)
        self.assertTrue(evaluator.run_solution())

        # Validate that the expected number of calls and results are present
        self.assertEqual(add_info.call_count, len(expected_messages))

        for res in updater_results:
            # print("MATCH RESULT:\n{}\n".format(res))
            found = False
            for i in range(len(expected_messages)):
                if expected_messages[i] == res["message"]:
                    del expected_messages[i]
                    found = True
                    break
            self.assertTrue(found, msg="Found unexpected message '{}'!".format(res["message"]))
        self.assertEqual(len(expected_messages), 0, msg="Remaining messages: {}".format(expected_messages))

    def test_two_player_game(self):
        evaluator = self.get_evaluator("games_two_player_uttt.json", config.LANGUAGE_CPP)
        evaluator.create_sandbox_dir()

        # Configure fake paths to the tester and its executable and compile it
        evaluator.path_tester_source = os.path.join(self.PATH_FIXTURES, "UTTT/Tester/UltimateTTTTester.cpp")
        evaluator.path_tester_executable = os.path.join(config.PATH_TESTERS, "UltimateTTTTester.o")
        compilation_status = Compiler.compile(
            language=config.LANGUAGE_CPP,
            path_source=evaluator.path_tester_source,
            path_executable=evaluator.path_tester_executable
        )
        self.assertEqual(compilation_status, "")

        # Configure fake paths to the solution and its executable and compile it
        evaluator.path_source = os.path.join(self.PATH_FIXTURES, "UTTT/Solutions/UltimateTTT.cpp")
        compilation_status = Compiler.compile(
            language=config.LANGUAGE_CPP,
            path_source=evaluator.path_source,
            path_executable=evaluator.path_executable
        )
        self.assertEqual(compilation_status, "")

        # Finally configure the sources of the opponents to be a different solutions
        # Solutions are chosen to cover different languages (C++, Java) and yield different results (win, lose, crash)
        match_sources = {
            "1": os.path.join(self.PATH_FIXTURES, "UTTT/Solutions/UltimateTTT.cpp"),
            "2": os.path.join(self.PATH_FIXTURES, "UTTT/Solutions/UltimateTTT.java"),
            "3": os.path.join(self.PATH_FIXTURES, "UTTT/Solutions/UltimateTTTGood.cpp"),
            "4": os.path.join(self.PATH_FIXTURES, "UTTT/Solutions/UltimateTTTCrash.java"),
            "5": os.path.join(self.PATH_FIXTURES, "UTTT/Solutions/UltimateTTTInvalid.java"),
            "6": os.path.join(self.PATH_FIXTURES, "UTTT/Solutions/UltimateTTTWeak.cpp"),
            "7": os.path.join(self.PATH_FIXTURES, "UTTT/Solutions/UltimateTTTSlow.py")
        }

        for match in evaluator.matches:
            opponent_source_path = match_sources[str(match["player_two_id"])]
            with open(opponent_source_path, "rt") as inp:
                opponent_source = inp.read()
            opponent_language = common.get_language_by_source_name(opponent_source_path)
            match["source"] = opponent_source
            match["language"] = opponent_language

        self.game_helper(
            evaluator=evaluator,
            expected_messages=[
                "The match ended in a draw.",  # Forward versus self
                "The match ended in a draw.",  # Reverse versus self
                "JavaPlayer won.",  # Forward versus Java
                "JavaPlayer won.",  # Reverse versus Java
                "W1nn3r won.",  # Forward versus Lose
                "W1nn3r won.",  # Reverse versus Lose
                "Cr4sh's solution crashed.",  # Forward versus Crash
                "Cr4sh's solution crashed.",  # Reverse versus Crash
                "Wr0ng wanted to play in a non-empty cell!",  # Forward versus Invalid
                "espr1t won.",  # Reverse versus Invalid
                "espr1t won.",  # Forward versus Weak
                "espr1t won.",  # Reverse versus Weak
                "5l33p3r's solution used more than the allowed 0.50 seconds.",  # Forward versus Slow
                "5l33p3r's solution used more than the allowed 0.50 seconds.",  # Reverse versus Slow
            ]
        )
