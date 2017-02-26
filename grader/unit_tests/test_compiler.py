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
        config.PATH_SANDBOX = "unit_tests/test_sandbox/"
        if not path.exists(config.PATH_SANDBOX):
            makedirs(config.PATH_SANDBOX)

    def tearDown(self):
        shutil.rmtree(config.PATH_SANDBOX)

    def test_compilation_timeout_fail(self):
        # Too slow compilation: over the 3 second limit (5.84s on the machine I'm currently writing this)
        language = "C++"
        path_source = "unit_tests/fixtures/TemplateFibo.cpp"
        path_executable = "unit_tests/test_sandbox/TemplateFibo.o"

        message = Compiler.compile(language, path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected to fail, but passed.")

    """ You may need to add g++ to your PATH in order for the C++ compilation to run """
    def test_cpp_successful_compilation(self):
        # Successful, returns an empty string as an error message
        language = "C++"
        path_source = "unit_tests/fixtures/HelloWorldCppOK.cpp"
        path_executable = "unit_tests/test_sandbox/HelloWorldCppOK.o"

        message = Compiler.compile(language, path_source, path_executable)
        self.assertEqual("", message, "The C++ compilation expected to pass, but failed.")

    def test_cpp_unsuccessful_compilation(self):
        # Unsuccessful, returns the compilation message as an error string
        language = "C++"
        path_source = "unit_tests/fixtures/HelloWorldCppCE.cpp"
        path_executable = "unit_tests/test_sandbox/HelloWorldCppCE.o"

        message = Compiler.compile(language, path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected error message, but passed successfully.")

    def test_java_successful_compilation(self):
        # Successful, returns an empty string as an error message
        language = "Java"
        path_source = "unit_tests/fixtures/HelloWorldJavaOK.java"
        path_executable = "unit_tests/test_sandbox/HelloWorldJavaOK.jar"

        message = Compiler.compile(language, path_source, path_executable)
        self.assertEqual("", message, "The Java compilation expected to pass, but failed.")
        self.assertTrue(path.exists(path_executable))

    def test_java_unsuccessful_compilation(self):
        # Unsuccessful, returns the compilation message as an error string
        language = "Java"
        path_source = "unit_tests/fixtures/HelloWorldJavaCE.java"
        path_executable = "unit_tests/test_sandbox/HelloWorldJavaCE.jar"

        message = Compiler.compile(language, path_source, path_executable)
        self.assertIn("error", message)
        self.assertNotEqual("", message, "The Java compilation expected error message, but passed successfully.")
        self.assertFalse(path.exists(path_executable))

    def test_java_different_class_name(self):
        # Unsuccessful, returns the compilation message as an error string
        language = "Java"
        path_source = "unit_tests/fixtures/HelloWorldJavaDifferentClassName.java"
        path_executable = "unit_tests/test_sandbox/HelloWorldJavaDifferentClassName.jar"

        message = Compiler.compile(language, path_source, path_executable)
        self.assertNotIn("error", message)
        self.assertEqual("", message, "The Java compilation expected to pass, but failed.")
        self.assertTrue(path.exists(path_executable))
