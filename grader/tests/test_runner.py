"""
Tests whether the runner is behaving as expected.

1. Executing simple commands
    >> simple run
    >> input is passed correctly
    >> output is returned correctly
    >> stderr is returned correctly
    >> input and output limits
    >> overhead (time wasted in miscellaneous work)
    >> privileged flag is being applied
2. Executing timed commands
    >> /usr/bin/time parsing
    >> /usr/bin/timeout terminating with SIGTERM
    >> /usr/bin/timeout terminating with SIGKILL (catching SIGTERM)
    >> input is passed correctly
    >> output is returned correctly
    >> return code is returned properly
    >> execution time is returned properly
    >> execution memory is returned properly
    >> stderr ignored by default
    >> stderr mixed with stdout on request
    >> privileged flag is being applied
3. Executing complex programs
    >> input is passed correctly
    >> output is returned correctly
    >> return code is returned properly
    >> execution time is returned properly
    >> execution memory is returned properly
    >> stderr ignored by default
    >> stderr mixed with stdout on request
    >> privileged flag is being applied
    >> args are being appended
    >> executable is being copied to sandbox
    >> time and memory offsets are being applied
4. Time and memory offsets
    >> Time and Memory offsets for "Hello, World!" program in each language
    >> Time and Memory offsets for "Hello, World!" program with many includes in each language
    >> Time and Memory offsets for a more complex program in each language
    >> Time and Memory offsets for a more complex program with many includes in each language

"""

import shutil
import os
from unittest import TestCase, mock

import config
import initializer
from runner import Runner
from sandbox import Sandbox


class TestRunner(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/runner/")

    # Do it this way instead of using a class decorator since otherwise the patching
    # is not active in the setUp() / tearDown() methods -- and we need it there as well
    patch_sandbox = mock.patch("config.PATH_SANDBOX", os.path.abspath("tests/test_sandbox/"))

    @classmethod
    def setUpClass(cls):
        initializer.init()

        cls.patch_sandbox.start()
        if not os.path.exists(config.PATH_SANDBOX):
            os.makedirs(config.PATH_SANDBOX)

    @classmethod
    def tearDownClass(cls):
        shutil.rmtree(config.PATH_SANDBOX)
        cls.patch_sandbox.stop()

    def test_simple_run(self):
        try:
            Runner.run(sandbox=Sandbox(), command="pwd")
        except Exception as ex:
            self.fail("Did not expect an exception: {}.".format(str(ex)))

    def test_output_is_returned_correctly(self):
        stdout_bytes, stderr_bytes = Runner.run(sandbox=Sandbox(), command="pwd")
        self.assertEqual(stdout_bytes.decode().strip(), "/home")
        self.assertEqual(stderr_bytes.decode().strip(), "")

    def test_input_is_passed_correctly(self):
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=Sandbox(), command="read -rd '' v; echo \"$v\";", input_bytes=b"Hello, World!")
        self.assertEqual(stderr_bytes.decode().strip(), "")
        self.assertEqual(stdout_bytes.decode().strip(), "Hello, World!")

