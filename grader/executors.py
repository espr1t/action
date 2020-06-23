import os
import psutil
import shutil
from queue import Queue
from threading import Lock
from dataclasses import dataclass, field

import config
import common

logger = common.get_logger(__file__)


@dataclass
class Executor:
    cpu: int
    user: int
    name: str
    path: str = field(init=False)

    def __post_init__(self):
        # Build the path to the executor's sandbox directory
        self.path = os.path.join(config.PATH_SANDBOX, self.name)


class Executors:
    _lock = Lock()
    _available = None
    _have_been_set_up = False
    _have_been_queued = False

    @staticmethod
    def init():
        if not Executors._have_been_set_up:
            Executors._setup_executors()
        if not Executors._have_been_queued:
            Executors._queue_executors()

    @staticmethod
    def clean():
        Executors._clean_executors()

    @staticmethod
    def get() -> Executor:
        # Initialize executors if not done already
        Executors.init()

        # Return one of the executors (or block until one is available)
        # Use a lock in order to be able to limit the number of simultaneously running
        # executors even further (e.g., to one) - useful for example for I/O heavy programs
        with Executors._lock:
            return Executors._available.get()

    @staticmethod
    def release(executor: Executor):
        Executors._available.put(executor)

    @staticmethod
    def _get_user_id(username: str):
        # Run `id` command as user <username> to get uid
        # `sudo -u <username> id` prints, for example, the following:
        # uid=1001(executor01) gid=1001(executor01) groups=1001(executor01)
        user_info = str(os.popen("sudo -u {} id 2>/dev/null".format(username)).read())
        if len(user_info.split()) < 1:
            return -1
        return int(user_info.split()[0].split("=")[1].split("(")[0])

    @staticmethod
    def _create_user(username):
        if os.system("useradd --shell /bin/bash -M {}".format(username)):
            logger.fatal("Cannot create user '{}'!".format(username))
            exit(-1)
        return Executors._get_user_id(username)

    @staticmethod
    def _disable_network_access(username):
        if os.system("iptables -A OUTPUT -p all -m owner --uid-owner {} -j DROP".format(username)):
            logger.warning("Cannot disable network access for user '{}'!".format(username))

#     @staticmethod
#     def _setup_cgroups(user_name):
#         # Set up the cgroups rule itself
#         cg_name = "actionexecutor"
#         cg_rule = """
# group {cg_name} {{
#     cpu {{
#         cpu.cfs_quota_us = 100000;
#     }}
#     memory {{
#         memory.limit_in_bytes = 2147483648;
#     }}
# }}  """.format(cg_name=cg_name, max_memory=config.MAX_EXECUTION_MEMORY).strip()
#         cgconfig = "/etc/cgconfig.conf"
#         if not os.path.exists(cgconfig) or cg_name not in open(cgconfig, "r").read():
#             with open(cgconfig, "at") as out:
#                 out.write(cg_rule)
#
#         # Set the cgrule to be applied for the user
#         cgrules = "/etc/cgrules.conf"
#         if not os.path.exists(cgrules) or user_name not in open(cgrules, "r").read():
#             with open(cgrules, "at") as out:
#                 out.write("{} memory {}\n".format(user_name, cg_name))

    @staticmethod
    def _umount_and_delete_recursive(path, base_dir=False):
        for child in os.listdir(path):
            if base_dir and (child == "home" or child == "time"):
                continue
            child_path = os.path.join(path, child)

            # Un-mount it if it is one of the mounted directories
            if common.is_mount(child_path):
                # logger.info("    -- unmounting directory {}".format(child_path))
                if os.system("sudo umount --recursive {}".format(child_path)) != 0:
                    logger.fatal("Could not umount directory {}!".format(child_path))
                    exit(-1)

            # Now it should be a simple file or directory. Delete it recursively.
            if os.path.isdir(child_path):
                Executors._umount_and_delete_recursive(child_path, False)
                try:
                    os.rmdir(child_path)
                except OSError:
                    logger.warning("    -- skipping '{}' (directory not empty)".format(child_path))
            else:
                logger.warning("    -- skipping '{}' (neither a file nor a directory)".format(child_path))

    @staticmethod
    def _clean_executor_dir(executor_path):
        """
        Delete the sandbox/executorXX/ directory recursively unmounting paths where needed
        """
        # Unmount and remove mounted directories
        Executors._umount_and_delete_recursive(path=executor_path, base_dir=True)

        # Delete recursively custom directories
        shutil.rmtree(os.path.join(executor_path, "home"))
        shutil.rmtree(os.path.join(executor_path, "time"))

        # Finally remove the entire executor directory (should be empty now)
        # This can be done in the recursion above, but we want an extra check that the cleanup succeeded.
        try:
            # logger.info("    -- deleting base directory...")
            os.rmdir(executor_path)
        except OSError:
            logger.error("Executor directory '{}' not empty after cleanup!".format(executor_path))

    @staticmethod
    def _clean_executors():
        with Executors._lock:
            for i in range(config.MAX_PARALLEL_EXECUTORS):
                executor = Executor(cpu=-1, user=-1, name="executor{:02d}".format(i + 1))
                logger.info("Cleaning up {}...".format(executor.name))

                # TODO: Should we remove the system users as well?

                # Clean the executor's directory (sandbox/executorXX) if it exists
                if os.path.exists(executor.path):
                    logger.info("  >> removing sandbox directory...")
                    Executors._clean_executor_dir(executor.path)
                logger.info("  >> completed!")

    @staticmethod
    def _create_executor_dir(executor_path):
        # Create the user's root directory
        os.mkdir(executor_path, 0o755)

        # Prepare the directory for chroot-ing by mounting vital system directories
        logger.info("  >> mounting system paths...")

        for mount_dir in ["bin", "lib", "lib64", "usr", "dev", "sys", "proc", "etc/alternatives"]:
            mount_source = "/{}".format(mount_dir)
            mount_destination = os.path.join(executor_path, mount_dir)
            if not os.path.exists(mount_destination):
                os.makedirs(mount_destination, 0o755)
            if os.system("sudo mount --rbind {} {}".format(mount_source, mount_destination)) != 0:
                logger.fatal("Could not mount '{}'!".format(mount_source))
                exit(-1)
            if os.system("sudo mount --make-rslave {}".format(mount_destination)) != 0:
                logger.fatal("Could not mount as rslave '{}'!".format(mount_source))
                exit(-1)

        # Create  a /home directory in which all user files are copied to
        # This is also the working directory in which commands are executed
        logger.info("  >> creating home directory...")
        os.mkdir(os.path.join(executor_path, "home"), 0o755)

        # Put custom time binary into a /time directory
        logger.info("  >> creating time directory...")
        os.mkdir(os.path.join(executor_path, "time"), 0o755)
        os.system("gcc -O2 -o {target_file} {source_file}".format(
            target_file=os.path.join(executor_path, "time/time"),
            source_file=os.path.abspath("time/time.c")
        ))
        if not os.path.exists(os.path.abspath("time/time.c")):
            logger.fatal("Could not compile system timer (time/time.c)!")
            exit(-1)

    @staticmethod
    def _setup_executors():
        with Executors._lock:
            # Skip if already done
            if Executors._have_been_set_up:
                return

            # Check if MAX_PARALLEL_EXECUTORS is set properly
            if config.MAX_PARALLEL_EXECUTORS > psutil.cpu_count(logical=True):
                logger.fatal("MAX_PARALLEL_EXECUTORS set to {} when number of CPU cores is {}.".format(
                    config.MAX_PARALLEL_EXECUTORS, psutil.cpu_count(logical=True)))
                exit(0)
            if config.MAX_PARALLEL_EXECUTORS > psutil.cpu_count(logical=False):
                logger.warning("MAX_PARALLEL_EXECUTORS set to {} when physical CPU cores are {}.".format(
                    config.MAX_PARALLEL_EXECUTORS, psutil.cpu_count(logical=False)))

            # Create the user and sandbox (chroot) directory for each executor
            for i in range(config.MAX_PARALLEL_EXECUTORS):
                executor = Executor(cpu=-1, user=-1, name="executor{:02d}".format(i + 1))
                logger.info("Setting up {}...".format(executor.name))

                # Check if a UNIX user with this username exists and create one if not
                executor.user = Executors._get_user_id(executor.name)
                if executor.user == -1:
                    logger.info("  >> creating user...")
                    executor.user = Executors._create_user(executor.name)

                # Disable the user's access to the internet
                Executors._disable_network_access(executor.name)

                # Clean the executor's sandbox directory (sandbox/executorXX) if it exists
                if os.path.exists(executor.path):
                    logger.info("  >> cleaning sandbox directory...")
                    Executors._clean_executor_dir(executor.path)

                # Create the executor's sandbox directory (sandbox/executorXX) and prepare it for chrooting
                logger.info("  >> creating sandbox directory...")
                Executors._create_executor_dir(executor.path)

                logger.info("  >> completed!")

            logger.info("Executors set up successfully.")
            Executors._have_been_set_up = True

    @staticmethod
    def _queue_executors():
        with Executors._lock:
            # Skip if already done
            if Executors._have_been_queued:
                return

            # In order to avoid executors set on the same physical CPU (due to hyper-threading),
            # first assign CPU affinity to even ids, then odd ones
            affinity = list(range(0, psutil.cpu_count(), 2)) + list(range(1, psutil.cpu_count(), 2))

            # Create a queue which allows at most MAX_PARALLEL_EXECUTORS executors
            Executors._available = Queue(maxsize=config.MAX_PARALLEL_EXECUTORS)
            for i in range(config.MAX_PARALLEL_EXECUTORS):
                name = "executor{:02d}".format(i + 1)
                Executors._available.put(
                    Executor(cpu=affinity[i % len(affinity)], user=Executors._get_user_id(name), name=name)
                )

            logger.info("Executors queued successfully.")
            Executors._have_been_queued = True
