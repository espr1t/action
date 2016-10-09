"""
Tests whether the evaluator is behaving as expected.
"""
import json
import unittest
from unittest import mock
import shutil
from os import path, makedirs

import config
from evaluator import Evaluator, Progress


class TestEvaluator(unittest.TestCase):
    def setUp(self):
        config.PATH_SANDBOX = "test_sandbox/"
        if not path.exists(config.PATH_SANDBOX):
            makedirs(config.PATH_SANDBOX)

    def tearDown(self):
        shutil.rmtree(config.PATH_SANDBOX)

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

    def test_cleanup(self):
        # Create a new instance and write the source
        evaluator = self.get_evaluator("fixtures/problem_submit_ok.json")
        evaluator.create_sandbox_dir()

        # Assert the submit directory and source file are created
        self.assertTrue(path.exists(evaluator.path_sandbox))

        # Do the cleanup
        evaluator.cleanup()

        # Assert the submit directory and source file are removed
        self.assertFalse(path.exists(evaluator.path_sandbox))
