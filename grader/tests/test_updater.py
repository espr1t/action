"""
Tests whether the updater is behaving as expected.
"""
import unittest
from unittest import mock

import config
from time import sleep
from updater import Updater
from status import TestStatus

PATH_FIXTURES = "tests/fixtures/"
TESTS = [
    {"name": "Test.01.in", "position": 1},
    {"name": "Test.02.in", "position": 2},
    {"name": "Test.03.in", "position": 3},
]


class TestUpdater(unittest.TestCase):
    def test_set_results(self):
        updater = Updater("localhost", 1337, TESTS)
        results = updater.set_results(TestStatus.RUNTIME_ERROR)
        self.assertEqual(len(results), 3, "There must be exactly three results")
        for i in range(1, 3):
            self.assertEqual(results[i]["score"], 0)
            self.assertEqual(results[i]["status"], TestStatus.RUNTIME_ERROR.name)

    @mock.patch("common.send_request")
    def test_send_update(self, send_request_mock):
        updater = Updater("localhost", 1337, TESTS)
        updater.add_info(status=TestStatus.COMPILING)
        # An update should be sent shortly within the new info arrives
        sleep(0.1)
        self.assertTrue(send_request_mock.call_count == 1, "An update to the frontend is not being sent.")

    @mock.patch("common.send_request")
    def test_update_interval(self, send_request_mock):
        updater = Updater("localhost", 1337, TESTS)
        updater.add_info(status=TestStatus.COMPILING)
        # An update should be sent shortly within the new info arrives
        sleep(0.1)
        self.assertTrue(send_request_mock.call_count == 1, "An update to the frontend is not being sent.")

        updater.add_info(status=TestStatus.TESTING)
        # But it shouldn't be called too often...
        sleep(0.1)
        self.assertTrue(send_request_mock.call_count == 1, "Updates to the frontend are sent too often.")

        # ... instead, they should be called every UPDATE_INTERVAL seconds
        sleep(config.UPDATE_INTERVAL)
        self.assertTrue(send_request_mock.call_count == 2, "A second update was not sent after update INTERVAL.")
