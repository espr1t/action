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
"""

import os
import shutil
from unittest import TestCase, mock
from signal import SIGKILL

import pytest

import config
import initializer
from time import perf_counter
from runner import Runner
from sandbox import Sandbox
from compiler import Compiler
from wrapper import COMMAND_WRAPPER, parse_exec_info


class TestRunner(TestCase):
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

    @pytest.mark.order(2000)
    def test_input_is_passed_correctly(self):
        start_time = perf_counter()
        Runner.run(sandbox=Sandbox(), command="read txt; sleep $txt", input_bytes=b"0.5")
        self.assertGreaterEqual(perf_counter() - start_time, 0.5)
        self.assertLess(perf_counter() - start_time, 0.7)

    @pytest.mark.order(2001)
    def test_output_is_returned_correctly(self):
        stdout_bytes, stderr_bytes = Runner.run(sandbox=Sandbox(), command="cat", input_bytes=b"Hello, World!")
        self.assertEqual(stderr_bytes.decode().strip(), "")
        self.assertEqual(stdout_bytes.decode().strip(), "Hello, World!")

    @pytest.mark.order(2002)
    def test_stderr_is_returned_correctly(self):
        stdout_bytes, stderr_bytes = Runner.run(sandbox=Sandbox(), command="cat >&2;", input_bytes=b"Hello, World!")
        self.assertEqual(stdout_bytes.decode().strip(), "")
        self.assertEqual(stderr_bytes.decode().strip(), "Hello, World!")

    @pytest.mark.order(2003)
    def test_no_input_limit(self):
        # Create a ~100MB byte array and count the number of 'X' characters in it
        target_size = 100000000
        byte_pattern = bytes([byte for byte in os.urandom(1000000) if byte >= 32])
        input_bytes = byte_pattern * (target_size // len(byte_pattern))
        expected = byte_pattern.count(b'X') * (target_size // len(byte_pattern))

        # Verify the same number of occurrences of the letter 'X' reaches the command
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=Sandbox(), command="cat | tr -dc 'X' | wc -c", input_bytes=input_bytes
        )
        self.assertEqual(stderr_bytes.decode().strip(), "")
        self.assertEqual(int(stdout_bytes.decode().strip()), expected)

    @pytest.mark.order(2004)
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

    @pytest.mark.order(2005)
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
    @pytest.mark.order(2006)
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

    @pytest.mark.order(2007)
    def test_timing_command_wrapper(self):
        sandbox = Sandbox()
        sandbox.put_file(os.path.abspath("tests/fixtures/runner/handle_sigterm.py"))

        # With enough time the program completes successfully
        command = "pypy handle_sigterm.py"
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=sandbox, command=COMMAND_WRAPPER.format(command=command, timeout=0.3)
        )
        self.assertNotEqual(stdout_bytes.decode(), "")
        exit_code, exec_time, exec_memory = parse_exec_info(stderr_bytes.decode(), 0.3)
        self.assertEqual(exit_code, 0)
        self.assertTrue(0.0 <= exec_time <= 0.1)  # This is CPU time

        # If it runs longer than the timeout, it gets killed before printing anything
        command = "pypy handle_sigterm.py"
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=sandbox, command=COMMAND_WRAPPER.format(command=command, timeout=0.15)
        )
        self.assertEqual(stdout_bytes.decode(), "")
        exit_code, exec_time, exec_memory = parse_exec_info(stderr_bytes.decode(), 0.15)
        self.assertEqual(exit_code, SIGKILL)
        self.assertTrue(0.15 <= exec_time <= 0.17)  # This is clock time

        # Catching SIGTERM signal doesn't help
        command = "pypy handle_sigterm.py --handle"
        stdout_bytes, stderr_bytes = Runner.run(
            sandbox=sandbox, command=COMMAND_WRAPPER.format(command=command, timeout=0.15)
        )
        self.assertEqual(stdout_bytes.decode(), "")
        exit_code, exec_time, exec_memory = parse_exec_info(stderr_bytes.decode(), 0.15)
        self.assertEqual(exit_code, SIGKILL)
        self.assertTrue(0.15 <= exec_time <= 0.17)  # This is clock time

    @pytest.mark.order(2008)
    def test_run_command_io(self):
        run_result = Runner.run_command(sandbox=Sandbox(), command="cat", timeout=1.0, input_bytes=b"Hello, World!")
        self.assertEqual(run_result.exit_code, 0)
        self.assertLess(run_result.exec_time, 0.1)
        self.assertEqual(run_result.output.decode(), "Hello, World!")

    @pytest.mark.order(2009)
    def test_run_command_exit_code(self):
        run_result = Runner.run_command(sandbox=Sandbox(), command="exit 0", timeout=0.2)
        self.assertEqual(run_result.exit_code, 0)
        run_result = Runner.run_command(sandbox=Sandbox(), command="exit 42", timeout=0.2)
        self.assertEqual(run_result.exit_code, 42)
        run_result = Runner.run_command(sandbox=Sandbox(), command="factor 12345678900987654321", timeout=0.2)
        self.assertEqual(run_result.exit_code, 0)
        run_result = Runner.run_command(sandbox=Sandbox(), command="factor -42", timeout=0.2)
        self.assertEqual(run_result.exit_code, 1)

    @pytest.mark.order(2010)
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

    @pytest.mark.order(2011)
    def test_run_command_exec_memory(self):
        factor_input = "1234567890123456789012345678901"
        run_result = Runner.run_command(sandbox=Sandbox(), command="factor {}".format(factor_input), timeout=1.0)
        factor_output = "{}: 7742394596501 159455563099482401".format(factor_input)
        self.assertEqual(run_result.output.decode().strip(), factor_output)
        self.assertGreater(run_result.exec_memory, 1 << 20)  # More than 1MB
        self.assertLess(run_result.exec_memory, 1 << 23)     # And less than 8MB

        sandbox = Sandbox()
        sandbox.put_file(os.path.abspath("tests/fixtures/sandbox/mem_allocator.cpp"))
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

    @pytest.mark.order(2012)
    def test_run_command_stderr_handling(self):
        run_result = Runner.run_command(sandbox=Sandbox(), command="g++ -O2 -o foo foo.cpp", timeout=1.0)
        self.assertNotEqual(run_result.exit_code, 0)
        self.assertEqual(run_result.output.decode(), "")
        self.assertNotIn("Permission denied", run_result.output.decode())

        run_result = Runner.run_command(sandbox=Sandbox(), command="g++ -O2 -o foo foo.cpp", timeout=1.0, print_stderr=True)
        self.assertNotEqual(run_result.exit_code, 0)
        self.assertNotEqual(run_result.output.decode(), "")
        self.assertIn("Permission denied", run_result.output.decode())

    @pytest.mark.order(2013)
    def test_run_command_privileged_flag(self):
        sandbox = Sandbox()
        run_result = Runner.run_command(sandbox=sandbox, command="touch foo.txt", timeout=1.0)
        self.assertNotEqual(run_result.exit_code, 0)
        self.assertFalse(sandbox.has_file("foo.txt"))

        run_result = Runner.run_command(sandbox=sandbox, command="touch foo.txt", timeout=1.0, privileged=True)
        self.assertEqual(run_result.exit_code, 0)
        self.assertTrue(sandbox.has_file("foo.txt"))
