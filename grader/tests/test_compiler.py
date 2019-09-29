"""
Tests whether the compilation in various languages is working properly.
"""
import os
import unittest
import filecmp
import shutil

from compiler import Compiler

PATH_SANDBOX = "tests/test_sandbox/"
PATH_FIXTURES = "tests/fixtures/"


class TestCompiler(unittest.TestCase):
    def setUp(self):
        if not os.path.exists(PATH_SANDBOX):
            os.makedirs(PATH_SANDBOX)

    def tearDown(self):
        shutil.rmtree(PATH_SANDBOX)

    def test_compilation_timeout_okay(self):
        # Slow compilation, but within the 10 second limit
        path_source = os.path.join(PATH_FIXTURES, "LongCompilation.cpp")
        path_executable = os.path.join(PATH_SANDBOX, "LongCompilation.o")

        message = Compiler.compile("C++", path_source, path_executable)
        self.assertEqual("", message, "The C++ compilation failed, but expected to pass.")

    def test_compilation_timeout_fail(self):
        # Too slow compilation: over the 1 second limit (>20s on the grader machine)
        path_source = os.path.join(PATH_FIXTURES, "TemplateFibo.cpp")
        path_executable = os.path.join(PATH_SANDBOX, "TemplateFibo.o")

        message = Compiler.compile("C++", path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected to fail, but passed.")

    # You may need to add g++ to your PATH in order for the C++ compilation to run
    def test_cpp_successful_compilation(self):
        # Successful, returns an empty string as an error message
        path_source = os.path.join(PATH_SANDBOX, "HelloWorldCppOK.cpp")
        path_executable = os.path.join(PATH_SANDBOX, "HelloWorldCppOK.o")
        shutil.copyfile(os.path.join(PATH_FIXTURES, "HelloWorldCppOK.cpp"), path_source)

        message = Compiler.compile("C++", path_source, path_executable)
        self.assertEqual("", message, "The C++ compilation expected to pass, but failed.")

    def test_cpp_unsuccessful_compilation(self):
        # Unsuccessful, returns the compilation message as an error string
        path_source = os.path.join(PATH_SANDBOX, "HelloWorldCppCE.cpp")
        path_executable = os.path.join(PATH_SANDBOX, "HelloWorldCppCE.o")
        shutil.copyfile(os.path.join(PATH_FIXTURES, "HelloWorldCppCE.cpp"), path_source)

        message = Compiler.compile("C++", path_source, path_executable)
        self.assertNotEqual("", message, "The C++ compilation expected error message, but passed successfully.")

    def test_java_successful_compilation(self):
        # Successful, returns an empty string as an error message
        path_source = os.path.join(PATH_SANDBOX, "HelloWorldJavaOK.java")
        path_executable = os.path.join(PATH_SANDBOX, "HelloWorldJavaOK.jar")
        shutil.copyfile(os.path.join(PATH_FIXTURES, "HelloWorldJavaOK.java"), path_source)

        message = Compiler.compile("Java", path_source, path_executable)
        self.assertEqual("", message, "The Java compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))

    def test_java_unsuccessful_compilation(self):
        # Unsuccessful, returns the compilation message as an error string
        path_source = os.path.join(PATH_SANDBOX, "HelloWorldJavaCE.java")
        path_executable = os.path.join(PATH_SANDBOX, "HelloWorldJavaCE.jar")
        shutil.copyfile(os.path.join(PATH_FIXTURES, "HelloWorldJavaCE.java"), path_source)

        message = Compiler.compile("Java", path_source, path_executable)
        self.assertIn("error", message)
        self.assertNotEqual("", message, "The Java compilation expected error message, but passed successfully.")
        self.assertFalse(os.path.exists(path_executable))

    def test_java_different_class_name(self):
        # Unsuccessful, returns the compilation message as an error string
        path_source = os.path.join(PATH_SANDBOX, "HelloWorldJavaDifferentClassName.java")
        path_executable = os.path.join(PATH_SANDBOX, "HelloWorldJavaDifferentClassName.jar")
        shutil.copyfile(os.path.join(PATH_FIXTURES, "HelloWorldJavaDifferentClassName.java"), path_source)

        message = Compiler.compile("Java", path_source, path_executable)
        self.assertNotIn("error", message)
        self.assertEqual("", message, "The Java compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))

    def test_java_non_public_main_class(self):
        # Unsuccessful, returns the compilation message as an error string
        path_source = os.path.join(PATH_SANDBOX, "HelloWorldJavaNonPublic.java")
        path_executable = os.path.join(PATH_SANDBOX, "HelloWorldJavaNonPublic.jar")
        shutil.copyfile(os.path.join(PATH_FIXTURES, "HelloWorldJavaNonPublic.java"), path_source)

        message = Compiler.compile("Java", path_source, path_executable)
        self.assertNotIn("error", message)
        self.assertEqual("", message, "The Java compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))

    def test_python_successful_compilation(self):
        # Successful, returns an empty string as an error message
        path_source = os.path.join(PATH_SANDBOX, "HelloWorldPythonOK.py")
        path_executable = os.path.join(PATH_SANDBOX, "HelloWorldPythonOK.pyc")
        shutil.copyfile(os.path.join(PATH_FIXTURES, "HelloWorldPythonOK.py"), path_source)

        message = Compiler.compile("Python", path_source, path_executable)
        self.assertEqual("", message, "The Python compilation expected to pass, but failed.")
        self.assertTrue(os.path.exists(path_executable))
        # The "compiled" file should be the same as the source
        self.assertTrue(filecmp.cmp(path_source, path_executable, shallow=False))

    def test_python_unsuccessful_compilation(self):
        # Unsuccessful, returns the compilation message as an error string
        path_source = os.path.join(PATH_SANDBOX, "HelloWorldPythonCE.py")
        path_executable = os.path.join(PATH_SANDBOX, "HelloWorldPythonCE.pyc")
        shutil.copyfile(os.path.join(PATH_FIXTURES, "HelloWorldPythonCE.py"), path_source)

        message = Compiler.compile("Python", path_source, path_executable)
        self.assertNotEqual("", message, "The Python compilation expected error message, but passed successfully.")
