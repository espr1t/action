"""
Tests whether the evaluator is behaving as expected.
"""
import json
import unittest
from unittest import mock
import os
import shutil
import vcr
from evaluator import Evaluator

PATH_SANDBOX = "tests/test_sandbox/"
PATH_DATA = "tests/test_data/"
PATH_TESTS = "tests/test_data/tests/"
PATH_FIXTURES = "tests/fixtures/"
PATH_CASSETTES = "tests/fixtures/cassettes"


class TestEvaluator(unittest.TestCase):
    def setUp(self):
        if not os.path.exists(PATH_SANDBOX):
            os.makedirs(PATH_SANDBOX)
        if not os.path.exists(PATH_DATA):
            os.makedirs(PATH_DATA)
        if not os.path.exists(PATH_TESTS):
            os.makedirs(PATH_TESTS)

    def tearDown(self):
        shutil.rmtree(PATH_SANDBOX)
        shutil.rmtree(PATH_DATA)

    @mock.patch("config.PATH_SANDBOX", PATH_SANDBOX)
    def get_evaluator(self, data_file):
        with open(data_file) as file:
            data = json.loads(file.read())
            return Evaluator(data)

    def test_create_sandbox_dir(self):
        evaluator = self.get_evaluator(os.path.join(PATH_FIXTURES, "problem_submit_ok.json"))
        self.assertFalse(os.path.exists(evaluator.path_sandbox))
        evaluator.create_sandbox_dir()
        self.assertTrue(os.path.exists(evaluator.path_sandbox))

    @mock.patch("config.PATH_DATA", PATH_DATA)
    @mock.patch("config.PATH_TESTS", PATH_TESTS)
    @vcr.use_cassette(os.path.join(PATH_CASSETTES, "download_tests.yaml"))
    def test_download_tests(self):
        evaluator = self.get_evaluator(os.path.join(PATH_FIXTURES, "problem_submit_ok.json"))
        print("PATH TO SANDBOX: {}".format(evaluator.path_sandbox))

        # Assert none of the files is already present
        for test in evaluator.tests:
            self.assertFalse(os.path.exists(os.path.join(PATH_TESTS, test["inpHash"])))
            self.assertFalse(os.path.exists(os.path.join(PATH_TESTS, test["solHash"])))

        # Do the actual download
        evaluator.download_tests()

        # Assert all of the files are now present
        for test in evaluator.tests:
            self.assertTrue(os.path.exists(os.path.join(PATH_TESTS, test["inpHash"])))
            self.assertTrue(os.path.exists(os.path.join(PATH_TESTS, test["solHash"])))

    def test_write_source(self):
        evaluator = self.get_evaluator(os.path.join(PATH_FIXTURES, "problem_submit_ok.json"))
        self.assertFalse(os.path.isfile(evaluator.path_source))
        evaluator.create_sandbox_dir()
        evaluator.write_source(evaluator.source, evaluator.path_source)
        self.assertTrue(os.path.isfile(evaluator.path_source))
        with open(evaluator.path_source, "r") as file:
            self.assertEqual(evaluator.source, file.read())

    def test_cleanup(self):
        # Create a new instance and write the source
        evaluator = self.get_evaluator(os.path.join(PATH_FIXTURES, "problem_submit_ok.json"))
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
