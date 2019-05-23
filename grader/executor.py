"""
Executes a binary in a sandbox environment limiting its resources.
It limits the time and memory consumption, hard disk access, network access, thread/process creation.
"""

import psutil
from os import getcwd, path
from sys import platform
from time import sleep, perf_counter

import config
from status import TestStatus
from common import RunResult
from common import get_language_by_exec_name


if not platform.startswith("win32"):
    import resource
    from os import setuid

# TODO: Limit network usage
# TODO: Use Docker
# TODO: Add unit tests for network access
# TODO: Add unit tests for harddrive access
# TODO: Add unit tests for thread/process creation


class Executor:
    KILLED_TIME_LIMIT = 1
    KILLED_MEMORY_LIMIT = 2
    KILLED_RUNTIME_ERROR = 3

    # Use the resource library to limit process resources when on UNIX
    if not platform.startswith("win32"):
        @staticmethod
        def set_restrictions(time_limit, java):
            # Set the user to a low-privileged one, if we have the privileges for this
            try:
                setuid(1001)
            except OSError:
                pass

            # Kill the solution if it exceeds twice the time limit of the problem (wall-clock)
            resource.setrlimit(resource.RLIMIT_CPU, (time_limit * 2, time_limit * 2))

            # Kill the solution if it exceeds 1GB of memory
            if not java:
                resource.setrlimit(resource.RLIMIT_AS, (1073741824, 1073741824))

            # Set the stack to be 64MB
            resource.setrlimit(resource.RLIMIT_STACK, (67108864, 67108864))

            # Kill the solution if it writes more than 16MB of output to stdout/stderr
            resource.setrlimit(resource.RLIMIT_FSIZE, (16777216, 16777216))

            # Although setting a limit on the file handles doesn't quite work, it seems the programs cannot write
            # to files anyway. We create the sandbox dir with the user running the grader (typically root),
            # and since they are owned by a more privileged user, the program cannot write in it. It can, however,
            # write in its own home directory, so we should chroot the process.
            if not java:
                resource.setrlimit(resource.RLIMIT_NOFILE, (4, 4))

            # Deny creation of new processes and threads
            if not java:
                resource.setrlimit(resource.RLIMIT_NPROC, (1, 1))
            else:
                resource.setrlimit(resource.RLIMIT_NPROC, (999, 999))

            # Deny creation of core dump files
            resource.setrlimit(resource.RLIMIT_CORE, (0, 0))

            # Deny writing to message queues
            resource.setrlimit(resource.RLIMIT_MSGQUEUE, (0, 0))

    @staticmethod
    def exec_solution(sandbox, executable, inp_file, out_file, time_limit, memory_limit):
        exec_time = 0.0
        exec_memory = 0.0
        exit_code = None

        children_limit = 0
        thread_limit = config.THREAD_LIMIT_CPP
        time_offset = config.TIME_OFFSET_CPP
        memory_offset = config.MEMORY_OFFSET_CPP

        language = get_language_by_exec_name(executable)

        if language == "Java":
            thread_limit = config.THREAD_LIMIT_JAVA
            time_offset = config.TIME_OFFSET_JAVA
            memory_offset = config.MEMORY_OFFSET_JAVA
        if language == "Python":
            thread_limit = config.THREAD_LIMIT_PYTHON
            time_offset = config.TIME_OFFSET_PYTHON
            memory_offset = config.MEMORY_OFFSET_PYTHON

        # TODO: Figure out if this is still needed
        execution_time_limit = max(1.0, time_limit * 2)

        # Calling the executable doesn't work on Windows 10 Ubuntu Bash if we don't provide the full path
        executable = path.join(getcwd(), executable)

        args = []
        if language == "Java":
            xms = "-Xms{}k".format(1024)
            xmx = "-Xmx{}k".format(memory_limit // 1024)
            args = ["java", "-XX:-UseSerialGC", xms, xmx, "-jar", executable]
            executable = None
        elif language == "Python":
            args = ["python3", executable]
            executable = None

        start_time = perf_counter()
        if platform.startswith("win32"):
            process = psutil.Popen(args=args, executable=executable, cwd=sandbox, stdin=inp_file, stdout=out_file)
        else:
            process = psutil.Popen(args=args, executable=executable, cwd=sandbox, stdin=inp_file, stdout=out_file,
                                   preexec_fn=(lambda: Executor.set_restrictions(
                                       time_limit, language == "Java")))

        check_interval = config.EXECUTION_MIN_CHECK_INTERVAL

        while True:
            sleep(check_interval)
            # Exponentially increase the check interval, until a certain max time gap
            # This works fine for very short-lived programs as well as long-lived ones
            check_interval = min(config.EXECUTION_MAX_CHECK_INTERVAL, check_interval * 2)

            # Process already terminated
            if process.poll() is not None:
                break
            if not process.is_running():
                break

            # Process has hung up
            if perf_counter() - start_time > execution_time_limit:
                exit_code = Executor.KILLED_RUNTIME_ERROR
                process.kill()
                break

            try:
                # Update statistics
                exec_time = max(0, max(exec_time, process.cpu_times().user) - time_offset)
                # RSS should be available on both Windows and Unix
                exec_memory = max(0, max(exec_memory, process.memory_info().rss) - memory_offset)

                """
                # Consider using USS instead of PSS if available (currently it returns zeroes only)
                mem_info = process.memory_full_info()
                # Need to check with "in" for named tuple
                exec_memory = max(exec_memory, mem_info.pss if "pss" in mem_info else mem_info.rss)
                """

                # Spawning processes or threads
                # print("Running for {} seconds ({} children and {} threads)...".format(
                #    exec_time, len(process.children()), process.num_threads()))
                if len(process.children()) > children_limit or process.num_threads() > thread_limit:
                    exit_code = Executor.KILLED_RUNTIME_ERROR
                    process.kill()
                    break

                # Time Limit, kill the process
                if exec_time > time_limit:
                    exit_code = Executor.KILLED_TIME_LIMIT
                    process.kill()
                    break

                # Memory Limit, kill the process
                if exec_memory > memory_limit:
                    exit_code = Executor.KILLED_MEMORY_LIMIT
                    process.kill()
                    break

            except psutil.NoSuchProcess:
                break

        if exit_code is None:
            exit_code = process.wait(timeout=0.5)
            if exit_code is None:
                exit_code = Executor.KILLED_RUNTIME_ERROR

        error_message = ""

        # Leave the parent function decide what the test status will be
        return RunResult(TestStatus.TESTING, error_message, exit_code, exec_time, exec_memory, 0.0, "")
