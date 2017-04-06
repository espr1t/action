"""
Tests whether the game simulator is behaving as expected.
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


class TestGames(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        config.PATH_SANDBOX = "unit_tests/test_sandbox/"
        config.PATH_DATA = "unit_tests/test_data/"
        config.PATH_TESTS = "unit_tests/test_data/tests/"
        config.PATH_TESTERS = "unit_tests/test_data/testers/"
        if not path.exists(config.PATH_SANDBOX):
            makedirs(config.PATH_SANDBOX)
        if not path.exists(config.PATH_DATA):
            makedirs(config.PATH_DATA)
        if not path.exists(config.PATH_TESTS):
            makedirs(config.PATH_TESTS)

        import logging.config, yaml
        logging.config.dictConfig(yaml.load(open('logging.conf')))

    def test_end_to_end(self):
        with open("unit_tests/fixtures/tests_games.json") as file:
            data = json.loads(file.read())

        # Update player's source placeholder with actual code
        with open("unit_tests/fixtures/Snakes/Snakes.cpp") as source:
            data["source"] = source.read()

        # Update opponent's source placeholder with actual code
        with open("unit_tests/fixtures/Snakes/Opponent.cpp") as source:
            data["matches"][0]["source"] = source.read()

        evaluator = Evaluator(data)
        evaluator.evaluate()


