"""
Tests whether the evaluator is behaving as expected.
"""
import json
import unittest
from unittest import mock
import shutil
from os import path, makedirs
import vcr

import config
from compiler import Compiler
from evaluator import Evaluator, Progress


class TestEvaluator(unittest.TestCase):
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

    @mock.patch("requests.post")
    def test_send_update(self, requests_post_mock):
        evaluator = self.get_evaluator("fixtures/problem_submit_ok.json")
        evaluator.send_update(Progress.FINISHED, "Some message")
        self.assertTrue(requests_post_mock.called, "An update to the frontend is not being sent.")

    def test_create_sandbox_dir(self):
        evaluator = self.get_evaluator("fixtures/problem_submit_ok.json")
        self.assertFalse(path.exists(evaluator.path_sandbox))
        evaluator.create_sandbox_dir()
        self.assertTrue(path.exists(evaluator.path_sandbox))
        shutil.rmtree(evaluator.path_sandbox)

    @vcr.use_cassette("fixtures/cassettes/download_tests.yaml")
    def test_download_tests(self):
        evaluator = self.get_evaluator("fixtures/problem_submit_ok.json")

        # Assert none of the files is already present
        for test in evaluator.tests:
            self.assertFalse(path.exists(config.PATH_TESTS + test["inpHash"]))
            self.assertFalse(path.exists(config.PATH_TESTS + test["solHash"]))

        # Do the actual download
        evaluator.download_tests()

        # Assert all of the files are now present
        for test in evaluator.tests:
            self.assertTrue(path.exists(config.PATH_TESTS + test["inpHash"]))
            self.assertTrue(path.exists(config.PATH_TESTS + test["solHash"]))

    def test_write_source(self):
        evaluator = self.get_evaluator("fixtures/problem_submit_ok.json")
        self.assertFalse(path.isfile(evaluator.path_source))
        evaluator.create_sandbox_dir()
        evaluator.write_source()
        self.assertTrue(path.isfile(evaluator.path_source))
        with open(evaluator.path_source, "r") as file:
            self.assertEqual(evaluator.source, file.read())
        shutil.rmtree(evaluator.path_sandbox)

    def test_cpp_compile(self):
        """ You may need to add g++ to your PATH in order for this to run """

        # Successful, returns an empty string as an error message
        evaluator = self.get_evaluator("fixtures/problem_submit_ok.json")
        evaluator.create_sandbox_dir()
        evaluator.write_source()
        message = Compiler.compile(evaluator.path_source, evaluator.language, evaluator.path_executable)
        self.assertEqual(message, "", "The C++ compilation expected to pass, but failed.")
        evaluator.cleanup()

        # Unsuccessful, returns the compilation message as an error string
        evaluator = self.get_evaluator("fixtures/problem_submit_ce.json")
        evaluator.create_sandbox_dir()
        evaluator.write_source()
        message = Compiler.compile(evaluator.path_source, evaluator.language, evaluator.path_executable)
        self.assertNotEqual(message, "", "The C++ compilation expected error message, but passed successfully.")
        evaluator.cleanup()

    def test_cleanup(self):
        # Create a new instance and write the source
        evaluator = self.get_evaluator("fixtures/problem_submit_ok.json")
        evaluator.create_sandbox_dir()
        evaluator.write_source()

        # Assert the submit directory and source file are created
        self.assertTrue(path.exists(evaluator.path_sandbox))
        self.assertTrue(path.isfile(evaluator.path_source))

        # Do the cleanup
        evaluator.cleanup()

        # Assert the submit directory and source file are removed
        self.assertFalse(path.isfile(evaluator.path_source))
        self.assertFalse(path.exists(evaluator.path_sandbox))
