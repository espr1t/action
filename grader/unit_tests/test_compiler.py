"""
Tests whether the compilation in various languages is working properly.
"""
import unittest
from os import path, makedirs
import shutil

import config
from compiler import Compiler


class TestCompiler(unittest.TestCase):
    def setUp(self):
        # Make compilation time shorter for the unit tests to run more quickly
        config.MAX_COMPILATION_TIME = 0.5  # Seconds
        config.PATH_SANDBOX = "test_sandbox/"
        if not path.exists(config.PATH_SANDBOX):
            makedirs(config.PATH_SANDBOX)

    def tearDown(self):
        shutil.rmtree(config.PATH_SANDBOX)

    def test_compilation_timeout_fail(self):
        # Too slow compilation, over the 0.5 second limit (2.11s on the machine I'm currently writing this)
        language = "C++"
        path_source = "fixtures/TemplateFibo.cpp"
        path_executable = "test_sandbox/TemplateFibo.o"

        message = Compiler.compile(language, path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected to fail, but passed.")

    """ You may need to add g++ to your PATH in order for the C++ compilation to run """
    def test_cpp_successful_compilation(self):
        # Successful, returns an empty string as an error message
        language = "C++"
        path_source = "fixtures/HelloWorldOK.cpp"
        path_executable = "test_sandbox/HelloWorldOK.o"

        message = Compiler.compile(language, path_source, path_executable)
        self.assertEqual("", message, "The C++ compilation expected to pass, but failed.")

    def test_cpp_unsuccessful_compilation(self):
        # Unsuccessful, returns the compilation message as an error string
        language = "C++"
        path_source = "fixtures/HelloWorldCE.cpp"
        path_executable = "test_sandbox/HelloWorldCE.o"

        message = Compiler.compile(language, path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected error message, but passed successfully.")

