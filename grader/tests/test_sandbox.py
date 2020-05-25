"""
Tests whether the sandbox is behaving as expected:

1. Chroot setup
    >> is the working directory initially empty
    >> has the OS structure been mounted
2. File system access
    >> cannot create files and directories
    >> cannot remove files and directories
    >> cannot rm -rf /
    >> cannot redirect output to files
3. Network access
    >> cannot ping or wget the internet
    >> cannot ping or wget from localhost
4. User and process info
    >> cannot run commands with sudo
    >> appropriate ulimits are present
    >> processes are ran with very high priority
    >> scheduling algorithm is as expected
    >> effective user is executorXX
5. Sandbox API
    >> has file
    >> get file
    >> read file
    >> put file (with appropriate permissions)
    >> blocking and non-blocking runs
    >> privileged and non-privileged runs
6. Limits are applied
    >> output limit (stdout/stderr)
    >> input is unlimited
    >> fork bomb does nothing
    >> cpu limit of max 1 core per run
    >> hard memory limit of heap
    >> hard memory limit of stack
    >> hard timeout of a program (fail-safe)
7. High-level pre-requisites
    >> supported languages are available (C++, Java, Python)
    >> needed tools are available (/usr/bin/time, /usr/bin/timeout)
    >> limited simultaneously running sandboxes

"""

import os
from unittest import TestCase, mock
from time import perf_counter, sleep
from tempfile import TemporaryFile, NamedTemporaryFile
from concurrent.futures import ThreadPoolExecutor


import config
import initializer
from sandbox import Sandbox


class TestSandbox(TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/sandbox/")

    @classmethod
    def setUpClass(cls):
        initializer.init()

    @classmethod
    def tearDownClass(cls):
        pass

    @staticmethod
    def sandbox_helper(sandbox, command, privileged=False):
        stdout, stderr = TemporaryFile("wb+"), TemporaryFile("wb+")
        sandbox.execute(command=command, stdin_fd=None, stdout_fd=stdout, stderr_fd=stderr, privileged=privileged)

        stdout.seek(0); stdout_text = stdout.read().decode().strip()
        stderr.seek(0); stderr_text = stderr.read().decode().strip()

        # If running java or javac or jar the JVM prints an annoying message:
        # "Picked up JAVA_TOOL_OPTIONS: <actual options set by sandbox environment>
        # Remove it from the stderr if it is there
        if any(java in command for java in ["java", "javac", "jar"]):
            stdout_text = "\n".join(
                [line for line in stdout_text.splitlines() if not line.startswith("Picked up JAVA_TOOL_OPTIONS")]
            )
            stderr_text = "\n".join(
                [line for line in stderr_text.splitlines() if not line.startswith("Picked up JAVA_TOOL_OPTIONS")]
            )
        return stdout_text, stderr_text

    # ================================= #
    #           Chroot Setup            #
    # ================================= #
    def test_working_directory_is_empty(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ls")
        self.assertEqual("", stderr)
        self.assertEqual("", stdout)

    def test_working_directory_is_home(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="pwd")
        self.assertEqual("", stderr)
        self.assertEqual("/home", stdout)

    def test_chroot_has_proper_fs_tree(self):
        # Root directory has a proper chroot structure
        # List all entries in "/" folder (but skip "total...", thus not using -la)
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ls -ld /* /.*")
        self.assertEqual("", stderr)

        # All required system directories are present
        for mount_dir in ["bin", "dev", "etc", "lib", "lib64", "proc", "sys", "usr"]:
            self.assertIn(mount_dir, stdout)

        # All dirs (".", "..", "/home" and mounted directories) have the correct permissions
        self.assertEqual(stdout.count("xr-xr-x"), len(stdout.splitlines()))

    def test_sys_structure_is_mounted(self):
        # There are files in the mounted directories
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="cat /proc/uptime")
        self.assertEqual("", stderr)
        self.assertNotEqual("", stdout)

        # Sanity check that an error is printed on a missing file
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="cat /proc/foobarbaz")
        self.assertNotEqual("", stderr)
        self.assertEqual("", stdout)

    def test_cannot_chroot_second_time(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="chroot ..")
        self.assertIn("Operation not permitted", stderr)
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="sudo chroot ..")
        self.assertIn("you do not exist in the passwd database", stderr)

    # ================================= #
    #         File System Access        #
    # ================================= #
    def test_cant_touch_this(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="touch /proc/uptime")
        self.assertNotEqual("", stderr)
        self.assertEqual("", stdout)

    def test_cannot_make_directories(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="mkdir foo")
        self.assertNotEqual("", stderr)
        self.assertEqual("", stdout)

    def test_cannot_remove_directories(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="cd .. && rmdir home")
        self.assertNotEqual("", stderr)
        self.assertEqual("", stdout)

    def test_cannot_create_files(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="touch foo.txt")
        self.assertNotEqual("", stderr)
        self.assertEqual("", stdout)

    def test_cannot_redirect_to_files(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="echo bla > foo.txt")
        self.assertNotEqual("", stderr)
        self.assertEqual("", stdout)

    def test_cannot_rm_rf(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="rm -rf /")
        self.assertNotEqual("", stderr)
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="sudo rm -rf /")
        self.assertNotEqual("", stderr)

    def test_cp(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="cp /bin/bash .")
        self.assertIn("Permission denied", stderr)

    def test_mv(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="mv /bin/bash .")
        self.assertIn("Permission denied", stderr)

    def test_create_symlink(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ln -s /bin/bash bash")
        self.assertIn("Permission denied", stderr)

    def test_mount(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="mount /bin /usr")
        self.assertIn("only root can do that", stderr)

    # ================================= #
    #           Network Access          #
    # ================================= #
    @mock.patch("config.MAX_EXECUTION_TIME", 1.0)
    def test_no_ping_dns_resolving(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ping www.google.com")
        self.assertIn("Name or service not known", stderr)

    @mock.patch("config.MAX_EXECUTION_TIME", 1.0)
    def test_no_ping_to_ip_address(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ping 8.8.8.8")
        self.assertIn("Operation not permitted", stderr)

    @mock.patch("config.MAX_EXECUTION_TIME", 1.0)
    def test_no_wget_dns_resolving(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="wget www.google.com/robots.txt")
        self.assertIn("Name or service not known", stderr)

    @mock.patch("config.MAX_EXECUTION_TIME", 1.0)
    def test_no_wget_from_ip_address(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="wget 216.58.212.4/robots.txt")
        # The process should reach the MAX_EXECUTION_TIME and be killed (being stuck on "Connecting to...")
        self.assertEqual("Connecting to 216.58.212.4:80...", stderr.splitlines()[-1])

    @mock.patch("config.MAX_EXECUTION_TIME", 1.0)
    def test_no_localhost_access(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ping localhost")
        self.assertIn("Name or service not known", stderr)
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ping 127.0.0.1")
        self.assertIn("Operation not permitted", stderr)

    # ================================= #
    #        User and process info      #
    # ================================= #
    def test_cannot_run_commands_with_sudo(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="sudo ls -la")
        self.assertNotEqual("", stderr)
        self.assertEqual("", stdout)

    def test_prlimit_output(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="prlimit")

        expected = {
            "AS": [config.MAX_EXECUTION_MEMORY, config.MAX_EXECUTION_MEMORY, "bytes"],
            "CPU": [config.MAX_EXECUTION_TIME, config.MAX_EXECUTION_TIME, "seconds"],
            "DATA": [config.MAX_EXECUTION_MEMORY, config.MAX_EXECUTION_MEMORY, "bytes"],
            "FSIZE": [config.MAX_EXECUTION_OUTPUT, config.MAX_EXECUTION_OUTPUT, "bytes"],
            "NOFILE": [config.MAX_OPEN_FILES, config.MAX_OPEN_FILES, "files"],
            "NPROC": [config.MAX_PROCESSES, config.MAX_PROCESSES, "processes"],
            "RSS": [config.MAX_EXECUTION_MEMORY, config.MAX_EXECUTION_MEMORY, "bytes"],
            "STACK": [config.MAX_EXECUTION_STACK, config.MAX_EXECUTION_STACK, "bytes"],
        }

        lines = stdout.splitlines()
        for line in lines:
            tokens = line.split()
            if tokens[0] in expected:
                self.assertEqual(int(tokens[-3]), expected[tokens[0]][0])
                self.assertEqual(int(tokens[-2]), expected[tokens[0]][1])
                self.assertEqual(tokens[-1], expected[tokens[0]][2])

    # TODO: Remove if sticking with real-time priority
    # def test_niceness_level(self):
    #     stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="nice")
    #     print("OUTPUT: {}".format(stdout))
    #     self.assertEqual(str(config.PROCESS_PRIORITY), stdout)

    def test_executor_user(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="id -u")
        self.assertGreaterEqual(int(stdout), 1000)

    def test_scheduling_algorithm(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="chrt -p $$")
        self.assertIn("scheduling policy: SCHED_RR", stdout.splitlines()[0])  # Real-time UNIX scheduler
        # Priority 50 is in the middle of real-time
        self.assertIn("current scheduling priority: {}".format(config.PROCESS_PRIORITY_REAL), stdout.splitlines()[1])

    def test_process_info(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ps -o uid,pid,ppid,cls,pri,ni,rtprio -p $$")
        process_info = stdout.splitlines()[1].split()
        self.assertGreaterEqual(int(process_info[0]), 1000)  # User ID (again)
        self.assertEqual(process_info[3], "RR")  # Scheduling algorithm, RR = ROUND ROBIN
        self.assertEqual(process_info[5], "-")  # Nice level
        self.assertEqual(process_info[6], str(config.PROCESS_PRIORITY_REAL))  # Priority

    # ================================= #
    #            Sandbox API            #
    # ================================= #
    def test_has_file(self):
        sandbox = Sandbox()
        self.assertFalse(sandbox.has_file("foo.txt"))
        self.assertTrue(sandbox.has_file("../usr/bin/timeout"))

    def test_get_file(self):
        sandbox = Sandbox()
        self.assertFalse(os.path.isfile("./time_binary"))
        sandbox.get_file("../usr/bin/timeout", "./timeout_binary")
        self.assertTrue(os.path.isfile("./timeout_binary"))
        os.remove("./timeout_binary")

    def test_put_file(self):
        sandbox = Sandbox()
        self.assertFalse(sandbox.has_file("foo.txt"))
        sandbox.put_file("install_steps.txt", "foo.txt")
        self.assertTrue(sandbox.has_file("foo.txt"))

    def test_put_file_with_permissions_write(self):
        sandbox = Sandbox()
        self.assertFalse(sandbox.has_file("foo.txt"))
        sandbox.put_file("install_steps.txt", "foo.txt")
        self.assertTrue(sandbox.has_file("foo.txt"))
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="echo bar > foo.txt")
        self.assertNotEqual(stderr, "")  # No permissions to write
        sandbox.put_file("install_steps.txt", "foo.txt", 0o777)
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="echo bar > foo.txt")
        self.assertEqual(stderr, "")  # This time has permissions
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="cat foo.txt")
        self.assertEqual(stdout, "bar")  # Double check by printing the file's contents

    def test_put_file_with_permissions_exec(self):
        sandbox = Sandbox()
        sandbox.put_file("/bin/ls", "ls_binary")
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="./ls_binary ..")
        self.assertEqual(stderr, "")
        self.assertIn("bin", stdout)  # Should list parent folder

        sandbox.put_file("/bin/ls", "ls_binary", 0o766)
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="./ls_binary ..")
        self.assertIn("Permission denied", stderr)  # Should be unable to execute
        self.assertEqual(stdout, "")

    def test_read_file(self):
        sandbox = Sandbox()
        sandbox.put_file("install_steps.txt", "foo.txt")
        contents = sandbox.read_file("foo.txt").decode()
        self.assertIn("python", contents)

    def test_execute_blocking(self):
        sandbox = Sandbox()
        output = TemporaryFile(mode="w+b")
        start_time = perf_counter()
        sandbox.execute(command="sleep 0.2 ; echo foo", stdin_fd=None, stdout_fd=output, stderr_fd=None, blocking=True)
        self.assertGreaterEqual(perf_counter() - start_time, 0.2)
        self.assertEqual(output.tell(), 4)  # Already printed "foo\n"

    def test_execute_non_blocking(self):
        sandbox = Sandbox()
        output = TemporaryFile(mode="w+b")
        start_time = perf_counter()
        sandbox.execute(command="sleep 0.2 ; echo foo", stdin_fd=None, stdout_fd=output, stderr_fd=None, blocking=False)
        self.assertLess(perf_counter() - start_time, 0.1)
        self.assertEqual(output.tell(), 0)  # Haven't yet printed anything
        sleep(0.3)
        self.assertEqual(output.tell(), 4)  # But printing it eventually

    def test_privileged_execution(self):
        sandbox = Sandbox()
        sandbox.put_file("/bin/ls", "ls_binary", 0o744)

        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="./ls_binary ..")
        self.assertIn("Permission denied", stderr)  # Should be unable to execute
        self.assertEqual("", stdout)
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="./ls_binary ..", privileged=True)
        self.assertEqual("", stderr)  # But privileged user should be able to do it
        self.assertIn("bin", stdout)

    def test_privileged_deletion(self):
        sandbox = Sandbox()
        sandbox.put_file("/bin/ls", "ls_binary", 0o744)

        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="rm ls_binary")
        self.assertIn("Permission denied", stderr)  # Should be unable to execute
        self.assertTrue(sandbox.has_file("ls_binary"))
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="rm ls_binary", privileged=True)
        self.assertEqual("", stderr)  # But privileged user should be able to do it
        self.assertFalse(sandbox.has_file("ls_binary"))

    # ================================= #
    #          Applied ulimits          #
    # ================================= #
    def test_output_limit(self):
        sandbox = Sandbox()

        file_size = 1000000  # 1MB
        output = NamedTemporaryFile(mode="w+", delete=True)
        for i in range(file_size // 10):
            output.write("test test\n")
        output.flush()
        sandbox.put_file(output.name, "foo.txt")

        target_size = 0
        while target_size + file_size <= config.MAX_EXECUTION_OUTPUT:
            target_size += file_size
        stdout, stderr = self.sandbox_helper(
            sandbox=sandbox, command="for i in {{1..{}}}; do cat foo.txt; done;".format(target_size // file_size))
        self.assertEqual("", stderr)
        self.assertEqual(len(stdout), target_size - 1)

        target_size += file_size
        stdout, stderr = self.sandbox_helper(
            sandbox=sandbox, command="for i in {{1..{}}}; do cat foo.txt; done;".format(target_size // file_size))
        self.assertIn("File size limit exceeded", stderr)
        self.assertEqual(len(stdout), config.MAX_EXECUTION_OUTPUT)

    def test_no_input_limit(self):
        sandbox = Sandbox()

        file_size = 25000000  # 25MB
        output = NamedTemporaryFile(mode="w+", delete=True)
        message = "Without IT I'm just espr\n"
        for i in range(file_size // len(message)):
            output.write(message)
        output.flush()
        sandbox.put_file(output.name, "foo.txt")

        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="wc -c < foo.txt && wc -l < foo.txt")
        self.assertEqual("", stderr)
        self.assertEqual(len(stdout.splitlines()), 2)
        self.assertEqual(int(stdout.splitlines()[0]), file_size)
        self.assertEqual(int(stdout.splitlines()[1]), file_size // len(message))

    @mock.patch("config.MAX_EXECUTION_TIME", 0.5)
    def test_hard_timeout(self):
        start_time = perf_counter()
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="sleep 3; echo foo")
        self.assertEqual("", stdout)
        self.assertEqual("", stderr)
        self.assertTrue(0.4 < perf_counter() - start_time < 0.6)

    @mock.patch("config.MAX_EXECUTION_TIME", 1.0)
    def test_fork_bomb(self):
        # Run to see if it crashes the system and how many processes it spawns
        start_time = perf_counter()

        stdout, stderr = TemporaryFile(mode="w+"), TemporaryFile(mode="w+")
        sandbox = Sandbox()
        sandbox.execute(command=":(){ :|:& };:", stdin_fd=None, stdout_fd=stdout, stderr_fd=stderr, blocking=False)

        # Check number of processes by this executor and its CPU usage continuously
        # (but sleep for 0.01 seconds so we don't do it more than 100 times)
        iteration = 0
        max_cpu = 0.0
        max_processes = 0
        while perf_counter() - start_time < config.MAX_EXECUTION_TIME:
            if iteration % 2 == 0:
                ps_info = os.popen("ps -U {}".format(sandbox._executor.name)).read()
                max_processes = max(max_processes, len(ps_info.splitlines()) - 1)
            else:
                cpu_info = os.popen("top -b -n 1 -u {} | awk 'NR>7 {{ sum += $9; }} END {{ print sum; }}'".format(
                    sandbox._executor.name)).read()
                max_cpu = max(max_cpu, float(cpu_info))
            iteration += 1
            sleep(0.01)

        self.assertLess(perf_counter() - start_time, 1.2)
        self.assertLessEqual(max_processes, config.MAX_PROCESSES)
        self.assertLessEqual(max_cpu, 100.0)

        stdout.seek(0)
        self.assertEqual("", stdout.read())
        stderr.seek(0)
        self.assertIn("fork: retry: Resource temporarily unavailable", stderr.read())

        # At this point the sandbox is still running (as the fork bomb processes are detached)
        # Make sure that wait() kills it entirely
        self.assertTrue(sandbox.is_running())
        sandbox.wait(0.1)
        self.assertFalse(sandbox.is_running())

    @mock.patch("config.MAX_EXECUTION_TIME", 0.3)
    def test_sandbox_wait_kills_sleepers(self):
        stdout, stderr = TemporaryFile(mode="w+"), TemporaryFile(mode="w+")
        sandbox = Sandbox()
        sandbox.execute(command=":(){ :|:& };:", stdin_fd=None, stdout_fd=stdout, stderr_fd=stderr, blocking=False)

        # While the program is within its time limit it is at max processes
        sleep(0.2)
        self.assertTrue(sandbox.is_running())
        ps_info = os.popen("ps -U {}".format(sandbox._executor.name)).read()
        self.assertEqual(len(ps_info.splitlines()) - 1, config.MAX_PROCESSES)

        # What's worse, even after that they are still alive
        # (as they don't use much CPU, so are not affected by MAX_EXECUTION_TIME)
        sleep(0.2)
        self.assertTrue(sandbox.is_running())
        ps_info = os.popen("ps -U {}".format(sandbox._executor.name)).read()
        self.assertEqual(len(ps_info.splitlines()) - 1, config.MAX_PROCESSES)

        # However, wait() should terminate everything
        sandbox.wait(0.1)
        self.assertFalse(sandbox.is_running())
        ps_info = os.popen("ps -U {}".format(sandbox._executor.name)).read()
        self.assertEqual(len(ps_info.splitlines()) - 1, 0)

    def test_cpu_usage(self):
        sandbox = Sandbox()
        sandbox.put_file(os.path.join(self.PATH_FIXTURES, "factor.py"))
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command="/usr/bin/time --format '%U %P' python3 factor.py")
        self.assertIn("1000000000000037", stdout)
        self.assertEqual(len(stderr.splitlines()), 1)
        user_time = float(stderr.split()[0])
        percent_cpu = int(stderr.split()[1].split('%')[0])
        # Took at least half a second
        self.assertGreater(user_time, 0.5)
        # CPU usage was around 100%
        self.assertTrue(95 < percent_cpu <= 100)

    def test_memory_usage_heap(self):
        sandbox = Sandbox()
        sandbox.put_file(os.path.join(self.PATH_FIXTURES, "mem_allocator.cpp"))
        self.sandbox_helper(
            sandbox=sandbox, command="g++ -O2 -std=c++17 -w -s -o mem_allocator mem_allocator.cpp", privileged=True
        )
        self.assertTrue(sandbox.has_file("mem_allocator"))

        command = "/usr/bin/time --quiet --format='%M' /bin/bash -c \"./mem_allocator heap {}\" ; >&2 echo $?"

        targets = [10000000, 100000000, 500000000, 1000000000, 2000000000]  # 10MB, 100MB, 500MB, 1GB, 2GB
        for target in targets:
            stdout, stderr = self.sandbox_helper(sandbox=sandbox, command=command.format(target))
            exit_code, exec_memory = int(stderr.splitlines()[-1]), int(stderr.splitlines()[-2])
            self.assertEqual(exit_code, 0)
            self.assertTrue(target <= exec_memory * 1024 <= target + 5000000)  # Up to 5MB overhead for C++ libraries

        # Ten megabytes less than the threshold is okay
        target = config.MAX_EXECUTION_MEMORY - 10000000
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command=command.format(target))
        exit_code, exec_memory = int(stderr.splitlines()[-1]), int(stderr.splitlines()[-2])
        self.assertEqual(exit_code, 0)
        self.assertTrue(target <= exec_memory * 1024 <= target + 5000000)  # Up to 5MB overhead for C++ libraries

        # Ten megabytes more than the threshold is not okay
        target = config.MAX_EXECUTION_MEMORY + 10000000
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command=command.format(target))
        exit_code, exec_memory = int(stderr.splitlines()[-1]), int(stderr.splitlines()[-2])
        self.assertNotEqual(exit_code, 0)
        self.assertTrue(exec_memory * 1024 <= config.MAX_EXECUTION_MEMORY + 1024)

    def test_memory_usage_stack(self):
        sandbox = Sandbox()
        sandbox.put_file(os.path.join(self.PATH_FIXTURES, "mem_allocator.cpp"))
        self.sandbox_helper(
            sandbox=sandbox, command="g++ -O2 -std=c++17 -w -s -o mem_allocator mem_allocator.cpp", privileged=True
        )
        self.assertTrue(sandbox.has_file("mem_allocator"))

        command = "/usr/bin/time --quiet --format='%M' /bin/bash -c \"./mem_allocator stack {}\" ; >&2 echo $?"

        # Test different target stack sizes (should all be okay)
        targets = [1000000, 10000000, 50000000]  # 1MB, 10MB, 50MB
        for target in targets:
            stdout, stderr = self.sandbox_helper(sandbox=sandbox, command=command.format(target))
            exit_code, exec_memory = int(stderr.splitlines()[-1]), int(stderr.splitlines()[-2])
            self.assertEqual(exit_code, 0)
            self.assertTrue(target <= exec_memory * 1024 <= target + 5000000)  # Up to 5MB overhead for C++ libraries

        # Half a megabyte less than the threshold is okay
        target = config.MAX_EXECUTION_STACK - 500000
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command=command.format(target))
        exit_code, exec_memory = int(stderr.splitlines()[-1]), int(stderr.splitlines()[-2])
        self.assertEqual(exit_code, 0)
        self.assertTrue(target <= exec_memory * 1024 <= target + 5000000)  # Up to 5MB overhead for C++ libraries

        # Half a megabyte more than the threshold is not okay
        target = config.MAX_EXECUTION_STACK + 500000
        stdout, stderr = self.sandbox_helper(sandbox=sandbox, command=command.format(target))
        exit_code, exec_memory = int(stderr.splitlines()[-1]), int(stderr.splitlines()[-2])
        self.assertNotEqual(exit_code, 0)
        self.assertTrue(target <= exec_memory * 1024 <= target + 5000000)  # Up to 5MB overhead for C++ libraries

    # ================================= #
    #      High-level prerequisites     #
    # ================================= #
    def test_languages_are_available(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="g++")
        self.assertIn("g++: fatal error: no input files", stderr)

        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="java")
        self.assertIn("Usage: java [options]", stderr)

        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="javac")
        self.assertIn("Usage: javac <options> <source files>", stdout)

        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="jar")
        self.assertIn("Usage: jar [OPTION...]", stderr)

        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="python3 --version")
        self.assertIn("Python 3.", stdout)

    def test_time_command_available(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ls -la /usr/bin | grep -w time")
        self.assertEqual("", stderr)
        self.assertTrue(stdout.startswith("-rwx") and stdout.endswith("time"))

        command = "/usr/bin/time --quiet --format='%x %U %S %e %M' /bin/bash -c 'sleep 0.33; echo foo'"
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command=command)
        self.assertEqual(stdout, "foo")
        self.assertEqual(int(stderr.split()[0]), 0)  # Exit code
        self.assertGreaterEqual(float(stderr.split()[3]), 0.33)  # Clock time

    def test_timeout_command_available(self):
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command="ls -la /usr/bin | grep -w timeout")
        self.assertEqual("", stderr)
        self.assertTrue(stdout.startswith("-rwx") and stdout.endswith("timeout"))

        start_time = perf_counter()
        command = "/usr/bin/timeout 0.2s /bin/bash -c 'sleep 1.0; echo foo'"
        stdout, stderr = self.sandbox_helper(sandbox=Sandbox(), command=command)
        self.assertEqual(stdout, "")  # No output, killed before that
        self.assertLess(perf_counter() - start_time, 0.3)  # Killed shortly after the timeout

    # Up to MAX_PARALLEL_EXECUTORS run in parallel.
    # We expect if we run less than or equal to MAX_PARALLEL_EXECUTORS to take clock time equal to the longest
    # of them. If we run even a single one more, we expect the clock time to be roughly twice as long.
    def dummy_sleep_helper(self):
        start_time = perf_counter()
        sandbox = Sandbox()
        waiting_time = perf_counter() - start_time
        self.sandbox_helper(sandbox=sandbox, command="sleep 0.2 ; echo foo")
        return waiting_time

    def test_executors_under_limit(self):
        start_time = perf_counter()

        pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_EXECUTORS)
        futures = [pool.submit(self.dummy_sleep_helper) for _ in range(config.MAX_PARALLEL_EXECUTORS)]

        # Wait for all workers to complete and get the maximum waiting time for a Sandbox object
        max_waiting_time = 0.0
        for future in futures:
            max_waiting_time = max(max_waiting_time, future.result())

        # Expecting none of the workers to wait for another one to finish, this get a Sandbox object immediately
        self.assertLess(max_waiting_time, 0.1)
        # Waiting all of them to complete takes at least 0.2 seconds
        self.assertGreaterEqual(perf_counter() - start_time, 0.2)
        # But not much more than 0.2 seconds
        self.assertLess(perf_counter() - start_time, 0.3)

    def test_executors_over_limit(self):
        start_time = perf_counter()

        pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_EXECUTORS + 1)
        futures = [pool.submit(self.dummy_sleep_helper) for _ in range(config.MAX_PARALLEL_EXECUTORS + 1)]

        # Each of the processes runs in ~0.2s, but since we schedule them through a thread pool
        # we should reach this point much earlier. One of the threads should be blocked on waiting
        # for a sandbox, though.
        self.assertLess(perf_counter() - start_time, 0.1)

        # Wait for all workers to complete and get the maximum waiting time for a Sandbox object
        max_waiting_time = 0.0
        for future in futures:
            max_waiting_time = max(max_waiting_time, future.result())

        # Expecting one of the workers to wait for another one to finish before getting a Sandbox object
        self.assertGreaterEqual(max_waiting_time, 0.2)
        # Waiting all of them to complete takes at least 0.4 seconds (twice as much)
        self.assertGreaterEqual(perf_counter() - start_time, 0.4)
        # But not much more than 0.4 seconds
        self.assertLess(perf_counter() - start_time, 0.5)
