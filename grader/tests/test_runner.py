"""
Tests whether the runner is behaving as expected.

1. Executing simple commands
    >> simple run
    >> input is passed correctly
    >> output is returned correctly
    >> stderr is returned correctly
    >> input is passed along no matter how large
    >> overhead (time wasted in miscellaneous work)
    >> privileged flag is being applied
2. Executing timed commands
    >> execution info parsing
    >> execution is terminated when reaching timeout
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
    >> time and memory offsets are being applied
4. Time and memory offsets
    >> Time and Memory offsets for "Hello, World!" program in each language
    >> Time and Memory offsets for "Hello, World!" program with many includes in each language
    >> Time and Memory offsets for a more complex program in each language
    >> Time and Memory offsets for a more complex program with many includes in each language
"""

import os
import shutil
from unittest import TestCase, mock
from signal import SIGKILL

import config
import initializer
from time import perf_counter
from runner import Runner
from sandbox import Sandbox
from compiler import Compiler
from wrapper import COMMAND_WRAPPER, parse_exec_info


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

    # ================================= #
    #          Simple Commands          #
    # ================================= #
    def test_run(self):
        try:
            Runner.run(sandbox=Sandbox(), command="pwd")
        except Exception as ex:
            self.fail("Did not expect an exception: {}.".format(str(ex)))

    def test_input_is_passed_correctly(self):
        start_time = perf_counter()
        Runner.run(sandbox=Sandbox(), command="read txt; sleep $txt", input_bytes=b"0.5")
        self.assertGreaterEqual(perf_counter() - start_time, 0.5)
        self.assertLess(perf_counter() - start_time, 0.7)

    def test_output_is_returned_correctly(self):
        stdout_bytes, stderr_bytes = Runner.run(sandbox=Sandbox(), command="cat", input_bytes=b"Hello, World!")
        self.assertEqual(stderr_bytes.decode().strip(), "")
        self.assertEqual(stdout_bytes.decode().strip(), "Hello, World!")

    def test_stderr_is_returned_correctly(self):
        stdout_bytes, stderr_bytes = Runner.run(sandbox=Sandbox(), command="cat >&2;", input_bytes=b"Hello, World!")
        self.assertEqual(stdout_bytes.decode().strip(), "")
        self.assertEqual(stderr_bytes.decode().strip(), "Hello, World!")

    def test_no_input_limit(self):
        # Create a ~50MB byte array and count the number of 'X' characters in it
        letter_a = ord('A')
        input_bytes = bytes([letter_a + (byte & 31) for byte in os.urandom(50000000)])
        expected = input_bytes.count(ord('X'))

        # Verify the same number of occurrences of the letter 'X' reaches the command
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=Sandbox(), command="cat | grep -o X | wc -l", input_bytes=input_bytes
        )
        self.assertEqual(stderr_bytes.decode().strip(), "")
        self.assertEqual(int(stdout_bytes.decode().strip()), expected)

    def test_overhead(self):
        # Create a ~50MB byte array as input
        input_bytes = os.urandom(50000000)

        # Execute a very light command which shouldn't take any time and measure the overhead
        start_time = perf_counter()
        stdout_bytes, stderr_bytes = Runner.run(sandbox=Sandbox(), command="pwd", input_bytes=input_bytes)
        self.assertEqual(stderr_bytes.decode().strip(), "")
        self.assertEqual(stdout_bytes.decode().strip(), "/home")
        # The overhead for getting a sandbox, passing the input, and getting the output shouldn't be more than 0.2s
        self.assertLess(perf_counter() - start_time, 0.2)

    def test_run_privileged(self):
        sandbox = Sandbox()
        # Test that we can run() as privileged user
        self.assertFalse(sandbox.has_file("foo.txt"))
        stdout_bytes, stderr_bytes = Runner.run(sandbox=sandbox, command="touch foo.txt")
        self.assertNotEqual(stderr_bytes.decode(), "")
        self.assertFalse(sandbox.has_file("foo.txt"))
        stdout_bytes, stderr_bytes = Runner.run(sandbox=sandbox, command="touch foo.txt", privileged=True)
        self.assertEqual(stderr_bytes.decode(), "")
        self.assertTrue(sandbox.has_file("foo.txt"))

    # ================================= #
    #           Timed Commands          #
    # ================================= #
    def test_exec_info_parsing(self):
        # Valid - time is sum of user + sys times
        self.assertEqual(parse_exec_info("0.42 0.13 0.57 421337\n0\n", 0.42), (0, 0.55, 431449088))
        # Valid (killed by SIGKILL, use clock time instead)
        self.assertEqual(parse_exec_info("0.01 0.00 0.57 421337\n9\n", 0.5), (9, 0.57, 431449088))
        # Valid (non-zero exit code)
        self.assertEqual(parse_exec_info("0.42 0.13 0.57 421337\n11\n", 0.42), (11, 0.55, 431449088))

        # Invalid
        self.assertEqual(parse_exec_info("", 0.42), None)
        self.assertEqual(parse_exec_info("0.42 0.13 421337\n0", 0.42), None)
        self.assertEqual(parse_exec_info("0.42 0.13 0.57 421337", 0.42), None)
        self.assertEqual(parse_exec_info("0.42 0.13 foo 421337\n0", 0.42), None)
        self.assertEqual(parse_exec_info("0.42 0.13 0.57 421337\nbar", 0.42), None)
        self.assertEqual(parse_exec_info("0.42 0.13 0.57 421337\n0\nbaz", 0.42), None)

    def test_timing_command_wrapper(self):
        sandbox = Sandbox()
        sandbox.put_file(os.path.join(self.PATH_FIXTURES, "handle_sigterm.py"))

        # With enough time the program completes successfully
        command = "pypy3 handle_sigterm.py"
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=sandbox, command=COMMAND_WRAPPER.format(command=command, timeout=0.4)
        )
        self.assertNotEqual(stdout_bytes.decode(), "")
        exit_code, exec_time, exec_memory = parse_exec_info(stderr_bytes.decode(), 0.4)
        self.assertEqual(exit_code, 0)
        self.assertTrue(0.0 <= exec_time <= 0.1)  # This is CPU time
        self.assertTrue(2**20 <= exec_memory <= 2**25)  # Takes between 1MB and 32MB

        # If it runs longer than the timeout, it gets killed before printing anything
        command = "pypy3 handle_sigterm.py"
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=sandbox, command=COMMAND_WRAPPER.format(command=command, timeout=0.2)
        )
        self.assertEqual(stdout_bytes.decode(), "")
        exit_code, exec_time, exec_memory = parse_exec_info(stderr_bytes.decode(), 0.2)
        self.assertEqual(exit_code, SIGKILL)
        self.assertTrue(0.2 <= exec_time <= 0.22)  # This is clock time
        self.assertTrue(2**20 <= exec_memory <= 2**25)  # Takes between 1MB and 32MB

        # Catching SIGTERM signal doesn't help
        command = "pypy3 handle_sigterm.py --handle"
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=sandbox, command=COMMAND_WRAPPER.format(command=command, timeout=0.2)
        )
        self.assertEqual(stdout_bytes.decode(), "")
        exit_code, exec_time, exec_memory = parse_exec_info(stderr_bytes.decode(), 0.2)
        self.assertEqual(exit_code, SIGKILL)
        self.assertTrue(0.2 <= exec_time <= 0.22)  # This is clock time
        self.assertTrue(2**20 <= exec_memory <= 2**25)  # Takes between 1MB and 32MB

    def test_run_command_io(self):
        run_result = Runner.run_command(sandbox=Sandbox(), command="cat", timeout=1.0, input_bytes=b"Hello, World!")
        self.assertEqual(run_result.exit_code, 0)
        self.assertLess(run_result.exec_time, 0.1)
        self.assertEqual(run_result.output.decode(), "Hello, World!")

    def test_run_command_exit_code(self):
        run_result = Runner.run_command(sandbox=Sandbox(), command="exit 0", timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        run_result = Runner.run_command(sandbox=Sandbox(), command="exit 42", timeout=1.0)
        self.assertEqual(run_result.exit_code, 42)
        run_result = Runner.run_command(sandbox=Sandbox(), command="factor {}".format("1234567890" * 2), timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        run_result = Runner.run_command(sandbox=Sandbox(), command="factor {}".format("1234567890" * 5), timeout=1.0)
        self.assertEqual(run_result.exit_code, 1)

    def test_run_command_exec_time(self):
        prime1 = 420000000000001
        prime2 = 420000000000017
        input_bytes = "{}".format(prime1 * prime2).encode()

        start_time = perf_counter()
        run_result = Runner.run_command(sandbox=Sandbox(), command="factor $1", timeout=1.0, input_bytes=input_bytes)
        clock_time = perf_counter() - start_time
        # Output of factor is the number followed by its factors
        self.assertEqual(run_result.output.decode().strip(), "{}: {} {}".format(prime1 * prime2, prime1, prime2))
        # Clock time should be nearly the same as measured time
        self.assertAlmostEqual(clock_time, run_result.exec_time, delta=0.2)

    def test_run_command_exec_memory(self):
        factor_input = "1234567890123456789012345678901"
        run_result = Runner.run_command(sandbox=Sandbox(), command="factor {}".format(factor_input), timeout=1.0)
        factor_output = "{}: 7742394596501 159455563099482401".format(factor_input)
        self.assertEqual(run_result.output.decode().strip(), factor_output)
        self.assertGreater(run_result.exec_memory, 1 << 20)  # More than 1MB
        self.assertLess(run_result.exec_memory, 1 << 23)     # And less than 8MB

        sandbox = Sandbox()
        sandbox.put_file(os.path.join(self.PATH_FIXTURES, "..", "sandbox/mem_allocator.cpp"))
        run_result = Runner.run_command(sandbox=sandbox, timeout=10.0, privileged=True,
                                        command="g++ -O2 -std=c++17 -o mem_allocator mem_allocator.cpp")
        self.assertEqual(run_result.exit_code, 0)
        self.assertTrue(sandbox.has_file("mem_allocator"))

        run_result = Runner.run_command(sandbox=sandbox, command="./mem_allocator heap 50000000", timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertGreater(run_result.exec_memory, 50000000)
        self.assertLess(run_result.exec_memory, 55000000)  # Allowing up to 5MB overhead

        run_result = Runner.run_command(sandbox=sandbox, command="./mem_allocator heap 250000000", timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertGreater(run_result.exec_memory, 250000000)
        self.assertLess(run_result.exec_memory, 255000000)  # Allowing up to 5MB overhead

        run_result = Runner.run_command(sandbox=sandbox, command="./mem_allocator stack 10000000", timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertGreater(run_result.exec_memory, 10000000)
        self.assertLess(run_result.exec_memory, 15000000)  # Allowing up to 5MB overhead

        run_result = Runner.run_command(sandbox=sandbox, command="./mem_allocator stack 50000000", timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertGreater(run_result.exec_memory, 50000000)
        self.assertLess(run_result.exec_memory, 55000000)  # Allowing up to 5MB overhead

    def test_run_command_stderr_handling(self):
        run_result = Runner.run_command(sandbox=Sandbox(), command="g++ -O2 -o foo foo.cpp", timeout=1.0)
        self.assertNotEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode(), "")

        run_result = Runner.run_command(sandbox=Sandbox(), command="g++ -O2 -o foo foo.cpp", timeout=1.0, print_stderr=True)
        self.assertNotEqual(run_result.exit_code, 0)
        self.assertNotEqual(run_result.output.decode(), "")
        self.assertIn("fatal error", run_result.output.decode())

    def test_run_command_privileged_flag(self):
        sandbox = Sandbox()
        run_result = Runner.run_command(sandbox=sandbox, command="touch foo.txt", timeout=1.0)
        self.assertNotEqual(run_result.exit_code, 0)
        self.assertFalse(sandbox.has_file("foo.txt"))

        run_result = Runner.run_command(sandbox=sandbox, command="touch foo.txt", timeout=1.0, privileged=True)
        self.assertEqual(run_result.exit_code, 0)
        self.assertTrue(sandbox.has_file("foo.txt"))

    # ================================= #
    #          Complex Programs         #
    # ================================= #
    def test_run_program_io(self):
        path_source = os.path.join(self.PATH_FIXTURES, "reverser.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "reverser.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=32000000, timeout=1.0, input_bytes=b"espr1t")
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "t1rpse")

    def test_run_program_output_limit(self):
        path_source = os.path.join(self.PATH_FIXTURES, "outputlimit.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "outputlimit.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=64000000, timeout=1.0, input_bytes=None)
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(len(run_result.output.decode()), config.MAX_EXECUTION_OUTPUT)

    def test_run_program_exit_code(self):
        programs = [
            ("hello", 0),           # Exiting without errors
            ("exit42", 42),         # Exiting with custom error code
            ("divbyzero", 4),       # Exiting after division by zero
            ("outofbounds", 6),     # Exiting after accessing invalid memory
            ("toomuchmemory", 6),   # Exiting after trying to allocate too much memory
            ("timelimit", 9),       # Being killed after exceeding the time limit
            ("tryingtowrite", 11),  # Being killed after trying to write
        ]
        for program_name, expected_code in programs:
            path_source = os.path.join(self.PATH_FIXTURES, program_name + config.SOURCE_EXTENSION_CPP)
            path_executable = os.path.join(config.PATH_SANDBOX, program_name + config.EXECUTABLE_EXTENSION_CPP)
            status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
            self.assertEqual(status, "")

            run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                            memory_limit=32000000, timeout=0.5, input_bytes=None)
            self.assertEqual(run_result.exit_code, expected_code)

    def test_run_program_exec_time_sleeping(self):
        path_source = os.path.join(self.PATH_FIXTURES, "sleeper.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "sleeper.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # Sleeping programs don't waste CPU, thus have negligible exec_time (although high clock-time)
        start_time = perf_counter()
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=32000000, timeout=0.5, input_bytes=None)
        self.assertEqual(run_result.exit_code, 0)
        self.assertLess(run_result.exec_time, 0.1)
        self.assertGreaterEqual(perf_counter() - start_time, 0.4)
        self.assertLess(perf_counter() - start_time, 0.6)
        self.assertEqual(run_result.output.decode().strip(), "2075")

        # ... except if they don't exceed the time limit, in which case their clock time is recorded
        start_time = perf_counter()
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=32000000, timeout=0.3, input_bytes=None)
        self.assertEqual(run_result.exit_code, 9)
        self.assertGreaterEqual(run_result.exec_time, 0.29)
        self.assertLess(run_result.exec_time, 0.4)
        self.assertGreaterEqual(perf_counter() - start_time, 0.3)
        self.assertLess(perf_counter() - start_time, 0.5)
        self.assertEqual(run_result.output.decode().strip(), "")

    def test_run_program_exec_time_cpu_intensive(self):
        path_source = os.path.join(self.PATH_FIXTURES, "cpuintensive.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "cpuintensive.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # CPU intensive programs have their execution time recorded properly
        start_time = perf_counter()
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=32000000, timeout=0.5, input_bytes=None)
        self.assertEqual(run_result.exit_code, 0)
        self.assertGreaterEqual(run_result.exec_time, 0.29)
        self.assertGreaterEqual(perf_counter() - start_time, 0.3)
        self.assertLess(run_result.exec_time, 0.4)
        self.assertLess(perf_counter() - start_time, 0.5)
        self.assertNotEqual(run_result.output.decode().strip(), "")

    def test_run_program_stderr_handling(self):
        path_source = os.path.join(self.PATH_FIXTURES, "stderrprint.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "stderrprint.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # Printing to stderr is ignored
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=32000000, timeout=0.5, input_bytes=None)
        self.assertEqual(run_result.exit_code, 0)
        output_lines = run_result.output.decode().strip().splitlines()
        self.assertEqual(len(output_lines), 1)
        self.assertEqual(output_lines[0], "762077221461")

        # ... except if requested by us
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=32000000, timeout=0.5, input_bytes=None, print_stderr=True)
        self.assertEqual(run_result.exit_code, 0)
        output_lines = run_result.output.decode().strip().splitlines()
        self.assertEqual(len(output_lines), 2)
        self.assertEqual(output_lines[0], "1234567")
        self.assertEqual(output_lines[1], "762077221461")

    def test_run_program_privileged(self):
        path_source = os.path.join(self.PATH_FIXTURES, "tryingtowrite.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "tryingtowrite.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # Writing without privileges leads to an error
        sandbox = Sandbox()
        run_result = Runner.run_program(sandbox=sandbox, executable_path=path_executable,
                                        memory_limit=32000000, timeout=0.5, input_bytes=None)
        self.assertNotEqual(run_result.exit_code, 0)
        self.assertFalse(sandbox.has_file("foo.txt"))

        # But we can give privileges
        run_result = Runner.run_program(sandbox=sandbox, executable_path=path_executable,
                                        memory_limit=32000000, timeout=0.5, input_bytes=None, privileged=True)
        self.assertEqual(run_result.exit_code, 0)
        self.assertTrue(sandbox.has_file("foo.txt"))

    def test_run_program_args_are_appended(self):
        path_source = os.path.join(self.PATH_FIXTURES, "printargs.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "printargs.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # Empty string when no arguments are passed
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=32000000, timeout=0.5)
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "")

        # If there are arguments, prints their concatenation
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=32000000, timeout=0.5, args=["foo", "bar", "baz"])
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "foobarbaz")

    def test_run_program_memory_cpp(self):
        path_source = os.path.join(self.PATH_FIXTURES, "hello.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=64000000, timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        self.assertGreater(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 2000000)  # Max 2MB overhead using this function

    def test_run_program_memory_cpp_with_includes(self):
        path_source = os.path.join(self.PATH_FIXTURES, "hello_includes.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello_includes.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=64000000, timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        self.assertGreater(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 2000000)  # Max 2MB overhead using this function

    def test_run_program_memory_java(self):
        path_source = os.path.join(self.PATH_FIXTURES, "hello.java")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello.jar")
        status = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=64000000, timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        # Note that Java's Garbage Collector makes the process quite volatile (and unpredictable)
        # in terms of memory usage. Set more relaxed limitations for Java
        self.assertGreaterEqual(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 4000000)  # Max 4MB overhead using this function

    def test_run_program_memory_java_with_imports(self):
        path_source = os.path.join(self.PATH_FIXTURES, "hello_imports.java")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello_imports.jar")
        status = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=64000000, timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        # Note that Java's Garbage Collector makes the process quite volatile (and unpredictable)
        # in terms of memory usage. Set more relaxed limitations for Java
        self.assertGreaterEqual(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 4000000)  # Max 4MB overhead using this function

    def test_run_program_memory_py(self):
        path_source = os.path.join(self.PATH_FIXTURES, "hello.py")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello.py")
        status = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=64000000, timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        self.assertGreater(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 2000000)  # Max 2MB overhead using this function

    def test_run_program_memory_py_with_imports(self):
        self.maxDiff = None
        path_source = os.path.join(self.PATH_FIXTURES, "hello_imports.py")
        path_executable = os.path.join(config.PATH_SANDBOX, "hello_imports.py")
        status = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertEqual(status, "")

        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=128000000, timeout=1.0)
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "Hello, World!")
        self.assertGreater(run_result.exec_memory, 0)
        self.assertLess(run_result.exec_memory, 2000000)  # Max 2MB overhead using this function

    def test_run_program_memory_cpp_fifty(self):
        path_source = os.path.join(self.PATH_FIXTURES, "fifty.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "fifty.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        memory_target, memory_limit = 50000000, 64000000
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=memory_limit, timeout=1.0, input_bytes=str(memory_target).encode())
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "96886856")
        self.assertGreater(run_result.exec_memory, memory_target)
        self.assertLess(run_result.exec_memory, memory_limit)

    def test_run_program_memory_java_fifty(self):
        path_source = os.path.join(self.PATH_FIXTURES, "fifty.java")
        path_executable = os.path.join(config.PATH_SANDBOX, "fifty.jar")
        status = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual(status, "")

        memory_target, memory_limit = 50000000, 64000000
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=memory_limit, timeout=1.0, input_bytes=str(memory_target).encode())
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "96886856")
        self.assertGreater(run_result.exec_memory, memory_target)
        self.assertLess(run_result.exec_memory, memory_limit)

    def test_run_program_memory_python_fifty(self):
        path_source = os.path.join(self.PATH_FIXTURES, "fifty.py")
        path_executable = os.path.join(config.PATH_SANDBOX, "fifty.py")
        status = Compiler.compile(config.LANGUAGE_PYTHON, path_source, path_executable)
        self.assertEqual(status, "")

        memory_target, memory_limit = 50000000, 64000000
        run_result = Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                        memory_limit=memory_limit, timeout=1.0, input_bytes=str(memory_target).encode())
        self.assertEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode().strip(), "57864746")
        self.assertGreater(run_result.exec_memory, memory_target)
        self.assertLess(run_result.exec_memory, memory_limit)
