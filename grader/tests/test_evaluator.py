"""
Tests whether the evaluator is behaving as expected.
"""
import os
import shutil
import vcr
import logging
import config
from unittest import TestCase, mock
from tests.helper import get_evaluator


class TestEvaluator(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/evaluator")

    # Do it this way instead of using a class decorator since otherwise the patching
    # is not active in the setUp() / tearDown() methods -- and we need it there as well
    patch_tests = mock.patch("config.PATH_TESTS", os.path.abspath("tests/test_data/"))
    patch_sandbox = mock.patch("config.PATH_SANDBOX", os.path.abspath("tests/test_sandbox/"))

    def setUp(self):
        logging.getLogger("vcr").setLevel(logging.FATAL)

        self.patch_tests.start()
        self.patch_sandbox.start()

        if not os.path.exists(config.PATH_SANDBOX):
            os.makedirs(config.PATH_SANDBOX)
        if not os.path.exists(config.PATH_TESTS):
            os.makedirs(config.PATH_TESTS)

    def tearDown(self):
        shutil.rmtree(config.PATH_SANDBOX)
        shutil.rmtree(config.PATH_TESTS)

        self.patch_tests.stop()
        self.patch_sandbox.stop()

    def test_create_sandbox_dir(self):
        evaluator = get_evaluator(os.path.join(self.PATH_FIXTURES, "problem_submit_ok.json"))
        self.assertFalse(os.path.exists(evaluator.path_sandbox))
        evaluator.create_sandbox_dir()
        self.assertTrue(os.path.exists(evaluator.path_sandbox))

    @vcr.use_cassette("tests/fixtures/cassettes/download_tests.yaml")
    def test_download_tests(self):
        evaluator = get_evaluator(os.path.join(self.PATH_FIXTURES, "problem_submit_ok.json"))

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
        evaluator = get_evaluator(os.path.join(self.PATH_FIXTURES, "problem_submit_ok.json"))
        self.assertFalse(os.path.isfile(evaluator.path_source))
        evaluator.create_sandbox_dir()
        evaluator.write_source(evaluator.source, evaluator.path_source)
        self.assertTrue(os.path.isfile(evaluator.path_source))
        with open(evaluator.path_source, "rt") as file:
            self.assertEqual(evaluator.source, file.read())

    def test_cleanup(self):
        # Create a new instance and write the source
        evaluator = get_evaluator(os.path.join(self.PATH_FIXTURES, "problem_submit_ok.json"))
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
