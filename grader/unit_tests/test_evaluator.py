"""
Tests whether the evaluator is behaving as expected.
"""
import json
import unittest
from unittest import mock

from evaluator import Evaluator, Progress


class TestEvaluator(unittest.TestCase):

    def get_evaluator(self, data_file):
        with open(data_file) as file:
            data = json.loads(file.read())
            return Evaluator(data)

    @mock.patch("requests.post")
    def test_send_update(self, requests_post_mock):
        evaluator = self.get_evaluator("fixtures/problem_submit_ok.json")
        evaluator.send_update(Progress.FINISHED, "Some message")
        self.assertTrue(requests_post_mock.called, "An update to the frontend is not being sent.")
