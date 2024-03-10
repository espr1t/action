"""
Implements sandboxed environment for execution of binaries and commands.

Works in a chroot directory with only essential binaries and executes the
command with limited privileges. In order to achieve that the command is
executed as specially created users without rights to write files.
Additionally, the process has limited network access, CPU usage, memory,
stack size, file input/output, child processes, and others.
"""

import os
from typing import Optional

import common
import config
import shutil
import subprocess
from sys import platform
from workers import Workers
from threading import Lock

logger = common.get_logger(__file__)

# Only available on UNIX, so trick Windows into thinking these are valid.
if not platform.startswith("win32"):
    from resource import *
else:
    RLIMIT_AS = "as"
    RLIMIT_CORE = "core"
    RLIMIT_CPU = "cpu"
    RLIMIT_DATA = "data"
    RLIMIT_VMEM = "vmem"
    RLIMIT_FSIZE = "fsize"
    RLIMIT_LOCKS = "locks"
    RLIMIT_MEMLOCK = "memlock"
    RLIMIT_NOFILE = "nofile"
    RLIMIT_NPROC = "nproc"
    RLIMIT_RSS = "rss"
    RLIMIT_STACK = "stack"
    RLIMIT_MSGQUEUE = "msgqueue"
    RLIMIT_NICE = "nice"
    RLIMIT_RTPRIO = "rtprio"
    RLIMIT_RTTIME = "rttime"
    RLIMIT_SIGPENDING = "sigpending"

    def setrlimit(resource, value):
        logger.info("Trying to set resource {} to {}".format(resource, value))


class Sandbox:
    def __init__(self):
        # Get or wait for an available worker
        self._worker = Workers.get()

        # Define the path to the working directory
        self._path = os.path.join(self._worker.path, "home")

        # Process handle and saved execution result
        self._lock = Lock()
        self._process = None

        # Check if directory exists and everything is mounted
        self._check()

        # Clean or create the sandbox directory
        self._clean()

    def __del__(self):
        self.wait(timeout=0.2)
        Workers.release(self._worker)

    def _check(self):
        # Test if the home directory exits
        if not os.path.exists(self._path):
            logger.fatal(
                "Sandbox {} check failed: directory '{}' does not exist!".format(
                    self._worker.name, self._path
                )
            )
            exit(0)
        # Test that /bin exists and is mounted properly (other mount points should be there as well)
        if not common.is_mount(os.path.join(self._path, os.pardir, "bin")):
            logger.fatal(
                "Sandbox {} check failed: directory '/bin' is not mounted!".format(
                    self._worker.name
                )
            )
            exit(0)

    def _clean(self):
        # Delete and re-create the worker's /home directory
        # The users should not have write access to any other directory,
        # thus those directories should not be modified by previous runs.
        shutil.rmtree(self._path)
        os.mkdir(self._path, 0o755)

    def _set_restrictions(self, privileged):
        # Limit the solution to 2GB of memory
        setrlimit(RLIMIT_AS, (config.MAX_EXECUTION_MEMORY, config.MAX_EXECUTION_MEMORY))
        setrlimit(RLIMIT_DATA, (config.MAX_EXECUTION_MEMORY, config.MAX_EXECUTION_MEMORY))
        setrlimit(RLIMIT_RSS, (config.MAX_EXECUTION_MEMORY, config.MAX_EXECUTION_MEMORY))

        # Set the stack to be 64MB
        setrlimit(RLIMIT_STACK, (config.MAX_EXECUTION_STACK, config.MAX_EXECUTION_STACK))

        # Kill the solution if it writes more than a certain amount of output
        setrlimit(RLIMIT_FSIZE, (config.MAX_EXECUTION_OUTPUT, config.MAX_EXECUTION_OUTPUT))

        # Limit creation of new processes and threads
        # Unfortunately, we need to allow more than one for Java's garbage collector and
        # other miscellaneous tasks (sub-shells, etc.). Still, this prevents a fork bomb.
        setrlimit(RLIMIT_NPROC, (config.MAX_PROCESSES, config.MAX_PROCESSES))

        # Limit the number of open file handles
        # It is not a precise limit and is here only so that the executed command doesn't
        # do anything really crazy. The command is executed in a chrooted directory with
        # no writing privileges (directories and files are created by root with mask 755),
        # so this is a fail-safe mechanism.
        setrlimit(RLIMIT_NOFILE, (config.MAX_OPEN_FILES, config.MAX_OPEN_FILES))

        # Deny creation of core dump files
        setrlimit(RLIMIT_CORE, (0, 0))

        # Deny writing to message queues
        # setrlimit(RLIMIT_MSGQUEUE, (0, 0))

        # A hard limit of 5 minutes for running anything in a sandbox
        # This is a very crude fail-safe mechanism if something really goes wrong
        # (shouldn't happen in practice if everything is working as intended)
        cpu_limit = max(
            1, round(config.MAX_EXECUTION_TIME)
        )  # Tests may set this to non-integers, thus fix it
        setrlimit(RLIMIT_CPU, (cpu_limit, cpu_limit))

        # Increase the priority of the process (in case realtime scheduler is not available)
        os.nice(config.PROCESS_PRIORITY_NICE)

        # Leave at standard scheduler for the moment
        # (managing time and memory is more complex with realtime priority processes)
        """
        # Limit realtime priority to 50 (out of 99)
        setrlimit(RLIMIT_RTPRIO, (config.PROCESS_PRIORITY_REAL, config.PROCESS_PRIORITY_REAL))

        # As a fail-safe mechanism, if the timeout didn't work for some reason, kill
        # the solution using the hard limit (MAX_EXECUTION_TIME seconds) instead
        setrlimit(RLIMIT_RTTIME, (config.MAX_EXECUTION_TIME * 1000000, config.MAX_EXECUTION_TIME * 1000000))

        # Set the scheduler to the realtime one (avoid interrupts as much as possible)
        if not platform.startswith("win32"):
            # os.system("chrt --rr --all-tasks --verbose --pid {} {}".format(config.PROCESS_PRIORITY_REAL, os.getpid()))
            os.sched_setscheduler(0, os.SCHED_RR, os.sched_param(config.PROCESS_PRIORITY_REAL))

        # Set the amount of CPU a real-time process can use in a second
        # (default is 95%, change this to 99%)
        os.system("sysctl -q kernel.sched_rt_runtime_us=990000")
        """

        # Limit the process and its children to use a concrete CPU core
        os.system("taskset -p -c {} {} > /dev/null".format(self._worker.cpu, os.getpid()))

        # Move the process in the user's working directory
        os.chdir(self._path)

        if not privileged:
            # Disable the network
            os.unshare(os.CLONE_NEWNET)

            # Chroot the process in the current sandbox
            # (it's parent, actually, we are currently in /home)
            os.chroot(os.path.join(self._path, os.path.pardir))

            # Set the user to a more unprivileged one (workerXX)
            os.setgroups([])
            os.setgid(self._worker.user)
            os.setuid(self._worker.user)

        os.environ["PATH"] = "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
        # Reduces Java memory footprint to work with ulimit
        os.environ["MALLOC_ARENA_MAX"] = "4"
        # Another workaround for Java memory
        os.environ["JAVA_TOOL_OPTIONS"] = "-Xmx{}m -XX:MaxMetaspaceSize=256m".format(
            config.MAX_EXECUTION_MEMORY // 1048576 // 2
        )

        # print("Current process: {}".format(os.getpid()))

    # Checks if specified file exists on the sandbox
    def has_file(self, file_name):
        return os.path.exists(os.path.join(self._path, file_name))

    # Copies the file at <file_path> to the sandbox /home directory as <target_name>
    def put_file(self, file_path, target_name=None, mode=0o755):
        file_path_on_sandbox = os.path.join(
            self._path, target_name if target_name is not None else os.path.basename(file_path)
        )
        shutil.copyfile(file_path, file_path_on_sandbox)
        os.chmod(file_path_on_sandbox, mode)

    # Deletes a file from the sandbox's /home directory
    def del_file(self, file_path):
        complete_path = os.path.join(self._path, file_path)
        if not os.path.exists(complete_path):
            logger.error(
                "Requested to delete file '{}' which does not exist on sandbox.".format(file_path)
            )
        else:
            os.remove(complete_path)

    # Copies a file named <file_name> from sandbox /home directory to <target_path>
    def get_file(self, file_name, target_path):
        if not self.has_file(file_name):
            logger.error(
                "Requested to get file '{}' which does not exist on sandbox.".format(file_name)
            )
        else:
            shutil.copyfile(os.path.join(self._path, file_name), target_path)

    # Reads the file named <file_name> from sandbox /home directory and returns it as bytes
    def read_file(self, file_name):
        if not self.has_file(file_name):
            logger.error(
                "Requested to read file '{}' which does not exist on sandbox.".format(file_name)
            )
            return None
        with open(os.path.join(self._path, file_name), "rb") as byte_stream:
            return byte_stream.read()

    def is_running(self):
        # Check that at least one processes started by the worker user is running
        # (Please note that the first line of output is table column names, thus check for > 1)
        return len(os.popen("ps -U {}".format(self._worker.name)).read().strip().splitlines()) > 1

    def wait(self, timeout=None) -> int:
        exit_code = 0
        if self._process is not None and self._process.poll() is None:
            try:
                exit_code = self._process.wait(timeout)
                self._process = None
            except subprocess.TimeoutExpired:
                pass
        # Force kill all left-over processes (children, grandchildren, etc.)
        with (self._lock):
            if self._process is not None:
                if self.is_running():
                    self._process.kill()
                    exit_code = 9
                del self._process
            self._process = None
            # Some detached threads may still exist at this point (e.g., a fork bomb).
            # Run killall until all of them are gone.
            while self.is_running():
                os.system("killall --signal KILL --user {}".format(self._worker.name))
                exit_code = 9
        return exit_code

    def execute(
        self, command, stdin_fd, stdout_fd, stderr_fd, blocking=True, privileged=False
    ) -> Optional[int]:
        if self._process is not None:
            logger.error("Trying to run a second process while previous still running")
            return None

        # Run the command in a new process, limiting its resource usage
        # print("Executing command: {}".format(command))
        self._process = subprocess.Popen(
            args=command,
            shell=True,
            executable="/bin/bash",
            stdin=stdin_fd,
            stdout=stdout_fd,
            stderr=stderr_fd,
            preexec_fn=(lambda: self._set_restrictions(privileged)),
        )

        if blocking:
            return self.wait(config.MAX_EXECUTION_TIME)
        else:
            return None
