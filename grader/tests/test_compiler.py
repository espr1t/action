"""
Tests whether the compilation in various languages is working properly.
"""
import os
import filecmp
import shutil
from time import perf_counter
from unittest import TestCase, mock

import pytest

import config
from compiler import Compiler
import initializer


class TestCompiler(TestCase):
    PATH_SANDBOX = os.path.abspath("tests/test_sandbox/")
    PATH_FIXTURES = os.path.abspath("tests/fixtures/compilation/")

    @classmethod
    def setUpClass(cls):
        initializer.init()

    def setUp(self):
        if not os.path.exists(self.PATH_SANDBOX):
            os.makedirs(self.PATH_SANDBOX)

    def tearDown(self):
        shutil.rmtree(self.PATH_SANDBOX)

    @pytest.mark.order(3000)
    def test_compilation_timeout_okay(self):
        # Slow compilation, but within the time limit
        path_source = os.path.join(self.PATH_FIXTURES, "cpp/SlowCompilation.cpp")
        path_executable = os.path.join(self.PATH_SANDBOX, "SlowCompilation.o")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual("", message, "The C++ compilation failed, but expected to pass.")
        self.assertTrue(os.path.exists(path_executable))

    @pytest.mark.order(3001)
    @mock.patch("config.MAX_COMPILATION_TIME", 2.0)
    def test_compilation_timeout_fail(self):
        # Too slow compilation (actual compilation time is over 10s, but we limit it to 2s)
        path_source = os.path.join(self.PATH_FIXTURES, "cpp/VerySlowCompilation.cpp")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.o")

        start_time = perf_counter()
        message = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        compilation_time = perf_counter() - start_time

        self.assertFalse(os.path.exists(path_executable))
        self.assertNotEqual("", message, "The C++ compilation expected to fail, but passed.")
        self.assertTrue(message.startswith("Compilation exceeded the time limit"))
        self.assertLess(compilation_time, config.MAX_COMPILATION_TIME + 0.5)
        self.assertFalse(os.path.exists(path_executable))

    # You may need to add g++ to your PATH in order for the C++ compilation to run
    @pytest.mark.order(3002)
    def test_cpp_successful_compilation(self):
        path_source = os.path.join(self.PATH_FIXTURES, "cpp/HelloWorldOK.cpp")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.o")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual("", message, "The C++ compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))

    @pytest.mark.order(3003)
    def test_cpp_successful_compilation_with_cyrillic(self):
        path_source = os.path.join(self.PATH_FIXTURES, "cpp/HelloWorldOKCyrillic.cpp")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.o")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual("", message, "The C++ compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))

    @pytest.mark.order(3004)
    def test_cpp_successful_compilation_with_unicode(self):
        path_source = os.path.join(self.PATH_FIXTURES, "cpp/HelloWorldOKUnicode.cpp")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.o")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual("", message, "The C++ compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))

    @pytest.mark.order(3005)
    def test_cpp_unsuccessful_compilation(self):
        path_source = os.path.join(self.PATH_FIXTURES, "cpp/HelloWorldCE.cpp")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.o")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected error message, but passed successfully.")
        self.assertIn("error", message)
        self.assertFalse(os.path.exists(path_executable))

    @pytest.mark.order(3006)
    def test_cpp_empty_file(self):
        path_source = os.path.join(self.PATH_FIXTURES, "cpp/EmptyFile.cpp")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.o")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected error message, but passed successfully.")
        self.assertIn("Source is empty.", message)
        self.assertFalse(os.path.exists(path_executable))

    @pytest.mark.order(3007)
    def test_cpp_random_garbage(self):
        path_source = os.path.join(self.PATH_FIXTURES, "cpp/RandomGarbage.cpp")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.o")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected error message, but passed successfully.")
        self.assertIn("error", message)
        self.assertFalse(os.path.exists(path_executable))

    @pytest.mark.order(3008)
    def test_cpp_no_return(self):
        path_source = os.path.join(self.PATH_FIXTURES, "cpp/NoReturn.cpp")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.o")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected error message, but passed successfully.")
        self.assertIn("error", message)
        self.assertFalse(os.path.exists(path_executable))

    @pytest.mark.order(3009)
    def test_java_successful_compilation(self):
        start_time = perf_counter()
        path_source = os.path.join(self.PATH_FIXTURES, "java/HelloWorldOK.java")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.jar")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual("", message, "The Java compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))
        self.assertLess(perf_counter() - start_time, 5.0)  # 5 seconds should be enough for the compilation

    @pytest.mark.order(3010)
    def test_java_unsuccessful_compilation(self):
        path_source = os.path.join(self.PATH_FIXTURES, "java/HelloWorldCE.java")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.jar")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertNotEqual("", message, "The Java compilation expected to fail, but passed successfully.")
        self.assertIn("error", message)
        self.assertFalse(os.path.exists(path_executable))

    @pytest.mark.order(3011)
    def test_java_different_class_name(self):
        path_source = os.path.join(self.PATH_FIXTURES, "java/WithDifferentClassName.java")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.jar")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual("", message, "The Java compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))

    @pytest.mark.order(3012)
    def test_java_non_public_main_class(self):
        path_source = os.path.join(self.PATH_FIXTURES, "java/WithNonPublicClass.java")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.jar")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertNotEqual("", message, "The Java compilation expected to fail, but passed successfully.")
        self.assertIn("public class", message)
        self.assertFalse(os.path.exists(path_executable))

    @pytest.mark.order(3013)
    def test_java_empty_file(self):
        path_source = os.path.join(self.PATH_FIXTURES, "java/EmptyFile.java")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.jar")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertNotEqual("", message, "The Java compilation expected to fail, but passed successfully.")
        self.assertIn("Source is empty.", message)
        self.assertFalse(os.path.exists(path_executable))

    @pytest.mark.order(3014)
    def test_java_random_garbage(self):
        path_source = os.path.join(self.PATH_FIXTURES, "java/RandomGarbage.java")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.jar")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertNotEqual("", message, "The Java compilation expected to fail, but passed successfully.")
        self.assertIn("error", message)
        self.assertFalse(os.path.exists(path_executable))

    @pytest.mark.order(3015)
    def test_java_multiple_classes(self):
        path_source = os.path.join(self.PATH_FIXTURES, "java/WithMultipleClasses.java")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.jar")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual("", message, "The Java compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))

    @pytest.mark.order(3016)
    def test_java_multiple_non_public_classes(self):
        path_source = os.path.join(self.PATH_FIXTURES, "java/WithMultipleNonPublicClasses.java")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.jar")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertNotEqual("", message, "The Java compilation expected to fail, but passed successfully.")
        self.assertIn("public class", message)
        self.assertFalse(os.path.exists(path_executable))

    @pytest.mark.order(3017)
    def test_java_with_package_declaration(self):
        path_source = os.path.join(self.PATH_FIXTURES, "java/WithPackageDeclaration.java")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.jar")

        self.assertFalse(os.path.exists(path_executable))
        message = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual("", message, "The Java compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))

    @pytest.mark.order(3018)
    def test_python_successful_compilation(self):
        path_source = os.path.join(self.PATH_FIXTURES, "py/HelloWorldOK.py")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.py")

        message = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertEqual("", message, "The Python compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))
        # The "compiled" file should be the same as the source
        self.assertTrue(filecmp.cmp(path_source, path_executable, shallow=False))

    @pytest.mark.order(3019)
    def test_python_unsuccessful_compilation(self):
        path_source = os.path.join(self.PATH_FIXTURES, "py/HelloWorldCE.py")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.py")

        message = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertNotEqual("", message, "The Python compilation expected to fail, but passed successfully.")
        self.assertIn("error", message)

    @pytest.mark.order(3020)
    def test_python_empty_file(self):
        path_source = os.path.join(self.PATH_FIXTURES, "py/EmptyFile.py")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.py")

        message = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertNotEqual("", message, "The Python compilation expected to fail, but passed successfully.")
        self.assertIn("Source is empty.", message)

    @pytest.mark.order(3021)
    def test_python_only_whitespace(self):
        path_source = os.path.join(self.PATH_FIXTURES, "py/OnlyWhitespace.py")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.py")

        message = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertNotEqual("", message, "The Python compilation expected to fail, but passed successfully.")
        self.assertIn("Source is empty.", message)

    @pytest.mark.order(3022)
    def test_python_missing_module(self):
        path_source = os.path.join(self.PATH_FIXTURES, "py/MissingModule.py")
        path_executable = os.path.join(self.PATH_SANDBOX, "TargetName.py")

        message = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertNotEqual("", message, "The Python compilation expected to fail, but passed successfully.")
        self.assertIn("error", message)
