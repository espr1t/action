"""
Tests whether more complex programs behave and return as expected.

1. Executing complex programs
    >> input is passed correctly
    >> output is returned correctly
    >> return code is returned properly
    >> execution time is returned properly
    >> execution memory is returned properly
    >> stderr ignored by default
    >> stderr mixed with stdout on request
    >> privileged flag is being applied
    >> args are being appended
    >> time and memory offsets are being applied
2. Time and memory offsets
    >> Time and Memory offsets for "Hello, World!" program in each language
    >> Time and Memory offsets for "Hello, World!" program with many includes in each language
    >> Time and Memory offsets for a more complex program in each language
    >> Time and Memory offsets for a more complex program with many includes in each language
"""

import os
import shutil
from time import perf_counter
from unittest import TestCase, mock

import pytest

import config
import initializer
from compiler import Compiler
from runner import Runner
from sandbox import Sandbox


class TestPrograms(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/programs/")

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

    # ================================= #
    #          Complex Programs         #
    # ================================= #

    @pytest.mark.order(4000)
    def test_run_program_io(self):
        path_source = os.path.join(self.PATH_FIXTURES, "reverser.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "reverser.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=32000000,
            timeout=1.0,
            input_bytes=b"espr1t",
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "t1rpse")

    @pytest.mark.order(4001)
    def test_run_program_output_limit(self):
        path_source = os.path.join(self.PATH_FIXTURES, "outputlimit.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "outputlimit.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=64000000,
            timeout=1.0,
            input_bytes=None,
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(len(run_result.output.decode()), config.MAX_EXECUTION_OUTPUT)

    @pytest.mark.order(4002)
    def test_run_program_exit_code(self):
        programs = [
            ("hello", 0),  # Exiting without errors
            ("exit42", 42),  # Exiting with custom error code
            ("divbyzero", 4),  # Exiting after division by zero
            ("outofbounds", 6),  # Exiting after accessing invalid memory
            ("toomuchmemory", 6),  # Exiting after trying to allocate too much memory
            ("timelimit", 9),  # Being killed after exceeding the time limit
            ("tryingtowrite", 11),  # Being killed after trying to write
        ]
        for program_name, expected_code in programs:
            path_source = os.path.join(self.PATH_FIXTURES, program_name + config.SOURCE_EXTENSION_CPP)
            path_executable = os.path.join(
                config.PATH_SANDBOX, program_name + config.EXECUTABLE_EXTENSION_CPP
            )
            status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
            self.assertEqual(status, "")

            run_result = Runner.run_program(
                sandbox=Sandbox(),
                executable_path=path_executable,
                memory_limit=32000000,
                timeout=0.5,
                input_bytes=None,
            )
            self.assertEqual(run_result.exit_code, expected_code)

    @pytest.mark.order(4003)
    def test_run_program_exec_time_sleeping(self):
        path_source = os.path.join(self.PATH_FIXTURES, "sleeper.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "sleeper.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # Sleeping programs don't waste CPU, thus have negligible exec_time (although high clock-time)
        start_time = perf_counter()
        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=32000000,
            timeout=0.5,
            input_bytes=None,
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertLess(run_result.exec_time, 0.1)
        self.assertGreaterEqual(perf_counter() - start_time, 0.4)
        self.assertLess(perf_counter() - start_time, 0.6)
        self.assertEqual(run_result.output.decode().strip(), "2075")

        # ... except if they don't exceed the time limit, in which case their clock time is recorded
        start_time = perf_counter()
        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=32000000,
            timeout=0.3,
            input_bytes=None,
        )
        self.assertEqual(run_result.exit_code, 9)
        self.assertGreaterEqual(run_result.exec_time, 0.29)
        self.assertLess(run_result.exec_time, 0.4)
        self.assertGreaterEqual(perf_counter() - start_time, 0.3)
        self.assertLess(perf_counter() - start_time, 0.5)
        self.assertEqual(run_result.output.decode().strip(), "")

    @pytest.mark.order(4004)
    def test_run_program_exec_time_cpu_intensive(self):
        path_source = os.path.join(self.PATH_FIXTURES, "cpuintensive.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "cpuintensive.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # CPU intensive programs have their execution time recorded properly
        start_time = perf_counter()
        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=32000000,
            timeout=0.5,
            input_bytes=None,
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertGreaterEqual(run_result.exec_time, 0.29)
        self.assertGreaterEqual(perf_counter() - start_time, 0.3)
        self.assertLess(run_result.exec_time, 0.4)
        self.assertLess(perf_counter() - start_time, 0.5)
        self.assertNotEqual(run_result.output.decode().strip(), "")

    @pytest.mark.order(4005)
    def test_run_program_stderr_handling(self):
        path_source = os.path.join(self.PATH_FIXTURES, "stderrprint.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "stderrprint.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # Printing to stderr is ignored
        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=32000000,
            timeout=0.5,
            input_bytes=None,
        )
        self.assertEqual(run_result.exit_code, 0)
        output_lines = run_result.output.decode().strip().splitlines()
        self.assertEqual(len(output_lines), 1)
        self.assertEqual(output_lines[0], "762077221461")

        # ... except if requested by us
        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=32000000,
            timeout=0.5,
            input_bytes=None,
            print_stderr=True,
        )
        self.assertEqual(run_result.exit_code, 0)
        output_lines = run_result.output.decode().strip().splitlines()
        self.assertEqual(len(output_lines), 2)
        self.assertEqual(output_lines[0], "1234567")
        self.assertEqual(output_lines[1], "762077221461")

    @pytest.mark.order(4006)
    def test_run_program_privileged(self):
        path_source = os.path.join(self.PATH_FIXTURES, "tryingtowrite.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "tryingtowrite.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # Writing without privileges leads to an error
        sandbox = Sandbox()
        run_result = Runner.run_program(
            sandbox=sandbox,
            executable_path=path_executable,
            memory_limit=32000000,
            timeout=0.5,
            input_bytes=None,
        )
        self.assertNotEqual(run_result.exit_code, 0)
        self.assertFalse(sandbox.has_file("foo.txt"))

        # But we can give privileges
        run_result = Runner.run_program(
            sandbox=sandbox,
            executable_path=path_executable,
            memory_limit=32000000,
            timeout=0.5,
            input_bytes=None,
            privileged=True,
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertTrue(sandbox.has_file("foo.txt"))

    @pytest.mark.order(4007)
    def test_run_program_args_are_appended(self):
        path_source = os.path.join(self.PATH_FIXTURES, "printargs.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "printargs.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # Empty string when no arguments are passed
        run_result = Runner.run_program(
            sandbox=Sandbox(), executable_path=path_executable, memory_limit=32000000, timeout=0.5
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "")

        # If there are arguments, prints their concatenation
        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=32000000,
            timeout=0.5,
            args=["foo", "bar", "baz"],
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "foobarbaz")

    @pytest.mark.order(4008)
    def test_run_program_memory_cpp(self):
        path_source = os.path.join(self.PATH_FIXTURES, "hello.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(
            sandbox=Sandbox(), executable_path=path_executable, memory_limit=64000000, timeout=1.0
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        self.assertGreater(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 2000000)  # Max 2MB overhead using this function

    @pytest.mark.order(4009)
    def test_run_program_memory_cpp_with_includes(self):
        path_source = os.path.join(self.PATH_FIXTURES, "hello_includes.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello_includes.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(
            sandbox=Sandbox(), executable_path=path_executable, memory_limit=64000000, timeout=1.0
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        self.assertGreater(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 2000000)  # Max 2MB overhead using this function

    @pytest.mark.order(4010)
    def test_run_program_memory_java(self):
        path_source = os.path.join(self.PATH_FIXTURES, "hello.java")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello.jar")
        status = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(
            sandbox=Sandbox(), executable_path=path_executable, memory_limit=64000000, timeout=1.0
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        # Note that Java's Garbage Collector makes the process quite volatile (and unpredictable)
        # in terms of memory usage. Set more relaxed limitations for Java
        self.assertGreaterEqual(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 4000000)  # Max 4MB overhead using this function

    @pytest.mark.order(4011)
    def test_run_program_memory_java_with_imports(self):
        path_source = os.path.join(self.PATH_FIXTURES, "hello_imports.java")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello_imports.jar")
        status = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(
            sandbox=Sandbox(), executable_path=path_executable, memory_limit=64000000, timeout=1.0
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        # Note that Java's Garbage Collector makes the process quite volatile (and unpredictable)
        # in terms of memory usage. Set more relaxed limitations for Java
        self.assertGreaterEqual(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 4000000)  # Max 4MB overhead using this function

    @pytest.mark.order(4012)
    def test_run_program_memory_py(self):
        """
        This test is quite likely to be flaky, as it depends on the PyPy version / implementation.
        The JIT memory usage is quite unpredictable. This was much better with CPython, when this
        test was originally written.
        """
        path_source = os.path.join(self.PATH_FIXTURES, "hello.py")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello.py")
        status = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(
            sandbox=Sandbox(), executable_path=path_executable, memory_limit=10000000, timeout=1.0
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        self.assertGreaterEqual(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 2000000)  # Max 2MB overhead using this function

    @pytest.mark.order(4013)
    def test_run_program_memory_py_with_imports(self):
        """
        This test is quite likely to be flaky, as it depends on the PyPy version / implementation.
        The JIT memory usage is quite unpredictable. This was much better with CPython, when this
        test was originally written.
        """
        self.maxDiff = None
        path_source = os.path.join(self.PATH_FIXTURES, "hello_imports.py")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello_imports.py")
        status = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(
            sandbox=Sandbox(), executable_path=path_executable, memory_limit=10000000, timeout=1.0
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        self.assertGreaterEqual(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 2000000)  # Max 2MB overhead using this function

    @pytest.mark.order(4014)
    def test_run_program_memory_py_little_array(self):
        """
        This test is quite likely to be flaky, as it depends on the PyPy version / implementation.
        The JIT memory usage is quite unpredictable. This was much better with CPython, when this
        test was originally written.
        """
        self.maxDiff = None
        path_source = os.path.join(self.PATH_FIXTURES, "hello_little_array.py")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello_little_array.py")
        status = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(
            sandbox=Sandbox(), executable_path=path_executable, memory_limit=20000000, timeout=1.0
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, 380527782!")
        self.assertGreater(run_result.exec_memory, 0)  # Greater-than-zero memory
        self.assertLess(run_result.exec_memory, 10000000)  # Max 10MB for this program

    @pytest.mark.order(4015)
    def test_run_program_memory_cpp_fifty(self):
        path_source = os.path.join(self.PATH_FIXTURES, "fifty.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "fifty.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        memory_target, memory_limit = 50000000, 64000000
        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=memory_limit,
            timeout=1.0,
            input_bytes=str(memory_target).encode(),
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "96886856")
        self.assertGreater(run_result.exec_memory, memory_target)
        self.assertLess(run_result.exec_memory, memory_limit)

    @pytest.mark.order(4016)
    def test_run_program_memory_java_fifty(self):
        path_source = os.path.join(self.PATH_FIXTURES, "fifty.java")
        path_executable = os.path.join(config.PATH_SANDBOX, "fifty.jar")
        status = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual(status, "")

        memory_target, memory_limit = 50000000, 64000000
        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=memory_limit,
            timeout=1.0,
            input_bytes=str(memory_target).encode(),
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "96886856")
        self.assertGreater(run_result.exec_memory, memory_target)
        self.assertLess(run_result.exec_memory, memory_limit)

    @pytest.mark.order(4017)
    def test_run_program_memory_python_fifty(self):
        """
        This test is quite likely to be flaky, as it depends on the PyPy version / implementation.
        The JIT memory usage is quite unpredictable. This was much better with CPython, when this
        test was originally written.
        """
        path_source = os.path.join(self.PATH_FIXTURES, "fifty.py")
        path_executable = os.path.join(config.PATH_SANDBOX, "fifty.py")
        status = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertEqual(status, "")

        memory_target, memory_limit = 50000000, 64000000
        run_result = Runner.run_program(
            sandbox=Sandbox(),
            executable_path=path_executable,
            memory_limit=memory_limit,
            timeout=1.0,
            input_bytes=str(memory_target).encode(),
        )
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "79670172")
        self.assertGreater(run_result.exec_memory, memory_target)
        self.assertLess(run_result.exec_memory, memory_limit)
