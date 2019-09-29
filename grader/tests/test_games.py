"""
Tests whether the game simulator is behaving as expected.
"""

import json
import unittest
import shutil
import os
import warnings
import config
from evaluator import Evaluator
from executor import Executor

PATH_SANDBOX = "tests/test_sandbox/"
PATH_DATA = "tests/test_data/"
PATH_TESTS = "tests/test_data/tests/"
PATH_TESTERS = "tests/test_data/testers/"
PATH_FIXTURES = "tests/fixtures/"


class TestGames(unittest.TestCase):
    def setUp(self):
        if not os.path.exists(PATH_SANDBOX):
            os.makedirs(PATH_SANDBOX)
        if not os.path.exists(PATH_DATA):
            os.makedirs(PATH_DATA)
        if not os.path.exists(PATH_TESTS):
            os.makedirs(PATH_TESTS)

        config.PATH_TESTS = PATH_TESTS
        Executor.setup_containers(1)
        warnings.simplefilter("ignore", ResourceWarning)

    def tearDown(self):
        shutil.rmtree(PATH_SANDBOX)
        shutil.rmtree(PATH_DATA)

    def test_end_to_end(self):
        with open(os.path.join(PATH_FIXTURES, "tests_games.json")) as file:
            data = json.loads(file.read())

        # Update player's source placeholder with actual code
        with open(os.path.join(PATH_FIXTURES, "Snakes/Snakes.cpp")) as source:
            data["source"] = source.read()

        # Update opponent's source placeholder with actual code
        with open(os.path.join(PATH_FIXTURES, "Snakes/Opponent.cpp")) as source:
            data["matches"][0]["source"] = source.read()

        evaluator = Evaluator(data)
        evaluator.evaluate()
