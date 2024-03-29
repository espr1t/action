"""
Tests whether concurrent runs perform consistently under load when having heavy
requirement on CPU / Hard Disk / Memory

NOTE: The tests assume fair access to resources (CPU, Memory AND CACHE), which is achieved through
      partitioning of the cache lanes (the resctrl module and Intel RDT (cache lane masks)), which
      are defined in limiter.py, but are not required for the system to run, since this is supported
    (at the time of writing) by a few Intel processors only.
NOTE2: Even then these tests may be flaky.
"""
import pytest
import shutil
import os
from unittest import TestCase, mock
from concurrent.futures import ThreadPoolExecutor
from tempfile import NamedTemporaryFile

import config
import initializer
from runner import Runner
from sandbox import Sandbox
from compiler import Compiler


class TestConcurrency(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/concurrency/")

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

    @staticmethod
    def concurrent_helper(path_input, path_executable, memory_limit=2**27, timeout=10.0):
        with open(path_input, "rb") as inp:
            input_bytes = inp.read()
        return Runner.run_program(sandbox=Sandbox(), executable_path=path_executable,
                                  memory_limit=memory_limit, timeout=timeout, input_bytes=input_bytes)

    @staticmethod
    def get_num_runs(minimum_runs):
        return minimum_runs + config.MAX_PARALLEL_WORKERS - minimum_runs % config.MAX_PARALLEL_WORKERS + 1

    @pytest.mark.order(9000)
    def test_run_program_concurrent_cpu_access_small_tl(self):
        path_source = os.path.join(self.PATH_FIXTURES, "summer_cpu.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "summer_cpu.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        num_runs = self.get_num_runs(20)
        input_file = NamedTemporaryFile(mode="w+b", delete=False)
        input_file.write(b"100000000")
        input_file.flush()

        # Run the same thing over and over again as quickly as possible to see if there is any inconsistency
        times = [1e100] * num_runs
        pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_WORKERS)
        futures = [pool.submit(self.concurrent_helper, input_file.name, path_executable) for _ in range(num_runs)]
        for i in range(len(futures)):
            run_result = futures[i].result()
            self.assertEqual(run_result.exit_code, 0)
            self.assertEqual(int(run_result.output.decode().strip()), 50001661776328125)
            times[i] = round(run_result.exec_time, 2)
        input_file.close()

        print(times)
        print("TIME: {:.3f}s vs {:.3f}s".format(min(times), max(times)))

        # Best time should be almost identical to the worst time in order to consider the results consistent
        # (at most 10% difference or 0.05s, whichever larger)
        self.assertLessEqual(max(times), max(min(times) + 0.05, min(times) * 1.1))

    @pytest.mark.order(9001)
    def test_run_program_concurrent_cpu_access_large_tl(self):
        path_source = os.path.join(self.PATH_FIXTURES, "summer_cpu.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "summer_cpu.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        num_runs = self.get_num_runs(20)
        input_file = NamedTemporaryFile(mode="w+b", delete=False)
        input_file.write(b"500000000")
        input_file.flush()

        # Run the same thing over and over again as quickly as possible to see if there is any inconsistency
        times = [1e100] * num_runs
        pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_WORKERS)
        futures = [pool.submit(self.concurrent_helper, input_file.name, path_executable) for _ in range(num_runs)]
        for i in range(len(futures)):
            run_result = futures[i].result()
            self.assertEqual(run_result.exit_code, 0)
            self.assertEqual(int(run_result.output.decode().strip()), 250010718184821892)
            times[i] = round(run_result.exec_time, 2)
        input_file.close()

        print(times)
        print("TIME: {:.3f}s vs {:.3f}s".format(min(times), max(times)))

        # Best time should be almost identical to the worst time in order to consider the results consistent
        # (at most 10% difference or 0.05s, whichever larger)
        self.assertLessEqual(max(times), max(min(times) + 0.05, min(times) * 1.1))

    @pytest.mark.order(9002)
    def test_run_program_concurrent_hdd_access(self):
        # Cannot test concurrency if there is none
        if config.MAX_PARALLEL_WORKERS <= 1:
            return

        path_source = os.path.join(self.PATH_FIXTURES, "summer_hdd.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "summer_hdd.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        # Write many integers into a (big) file
        input_file = NamedTemporaryFile(mode="w+b", delete=False)
        num_runs = self.get_num_runs(20)
        target_size = 100000000  # 100MB
        number_list = [999999999 - i for i in range(target_size // 10)]
        expected_output = sum(number_list)

        input_file.write(str(number_list).replace('[', '').replace(']', '').replace(',', '').encode("ascii"))
        input_file.flush()
        # Actual file size should be +/- 1% of the target
        self.assertAlmostEqual(os.path.getsize(input_file.name) / target_size, 1.0, 2)

        times = [1e100] * num_runs
        # Run the same thing several times as quickly as possible to see if there is any significant increase
        pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_WORKERS)
        futures = [pool.submit(self.concurrent_helper, input_file.name, path_executable) for _ in range(num_runs)]
        for i in range(len(futures)):
            run_result = futures[i].result()
            self.assertEqual(run_result.exit_code, 0)
            self.assertEqual(int(run_result.output.decode().strip()), expected_output)
            times[i] = round(run_result.exec_time, 2)
        input_file.close()

        print(times)
        print("TIME: {:.3f}s vs {:.3f}s".format(min(times), max(times)))

        # Best time should be almost identical to the worst time in order to consider the results consistent
        # (at most 10% difference or 0.05s, whichever larger)
        self.assertLessEqual(max(times), max(min(times) + 0.05, min(times) * 1.1))

    @pytest.mark.order(9003)
    def test_run_program_concurrent_memory_access(self):
        path_source = os.path.join(self.PATH_FIXTURES, "summer_mem.cpp")
        path_executable = os.path.join(config.PATH_SANDBOX, "summer_mem.o")
        status = Compiler.compile(config.LANGUAGE_CPP, path_source, path_executable)
        self.assertEqual(status, "")

        num_runs = self.get_num_runs(20)
        input_file = NamedTemporaryFile(mode="w+b", delete=False)
        input_file.write(b"2")
        input_file.flush()

        # Run the same thing over and over again as quickly as possible to see if there is any inconsistency
        times = [1e100] * num_runs
        pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_WORKERS)
        futures = [pool.submit(self.concurrent_helper, input_file.name, path_executable) for _ in range(num_runs)]
        for i in range(len(futures)):
            run_result = futures[i].result()
            self.assertEqual(run_result.exit_code, 0)
            self.assertEqual(int(run_result.output.decode().strip()), 772983032)
            times[i] = round(run_result.exec_time, 2)
        input_file.close()

        print(times)
        print("TIME: {:.3f}s vs {:.3f}s".format(min(times), max(times)))

        # Best time should be almost identical to the worst time in order to consider the results consistent
        # (at most 10% difference or 0.1s, whichever larger)
        self.assertLessEqual(max(times), max(min(times) + 0.1, min(times) * 1.1))

    @pytest.mark.order(9004)
    def test_run_program_concurrent_cpu_access_java(self):
        path_source = os.path.join(self.PATH_FIXTURES, "Sheep.java")
        path_executable = os.path.join(config.PATH_SANDBOX, "Sheep.jar")
        status = Compiler.compile(config.LANGUAGE_JAVA, path_source, path_executable)
        self.assertEqual(status, "")

        num_runs = self.get_num_runs(20)
        input_file = os.path.join(self.PATH_FIXTURES, "Sheep.in")

        # Run the same thing over and over again as quickly as possible to see if there is any inconsistency
        times = [1e100] * num_runs
        pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_WORKERS)
        futures = [pool.submit(self.concurrent_helper, input_file, path_executable) for _ in range(num_runs)]
        for i in range(len(futures)):
            run_result = futures[i].result()
            self.assertEqual(run_result.exit_code, 0)
            self.assertEqual(int(run_result.output.decode().strip()), 3800)
            times[i] = round(run_result.exec_time, 2)

        print(times)
        print("TIME: {:.3f}s vs {:.3f}s".format(min(times), max(times)))

        # Best time should be almost identical to the worst time in order to consider the results consistent
        # (at most 10% difference or 0.1s, whichever larger)
        self.assertLessEqual(max(times), max(min(times) + 0.1, min(times) * 1.1))
