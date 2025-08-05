import os
import psutil
import shutil
from time import sleep
from queue import Queue
from threading import Lock
from dataclasses import dataclass, field

import config
import common
import limiter

logger = common.get_logger(__file__)


@dataclass
class Worker:
    cpu: int
    user: int
    name: str
    path: str = field(init=False)

    def __post_init__(self):
        # Build the path to the worker's sandbox directory
        self.path = os.path.join(config.PATH_SANDBOX, self.name)


class Workers:
    _lock = Lock()
    _available = None
    _have_been_set_up = False
    _have_been_queued = False

    @staticmethod
    def init():
        if not Workers._have_been_set_up:
            Workers._setup_workers()
        if not Workers._have_been_queued:
            Workers._queue_workers()

    @staticmethod
    def clean():
        Workers._clean_workers()
        logger.info("Workers cleaned successfully.")

    @staticmethod
    def get() -> Worker:
        # Initialize workers if not done already
        Workers.init()

        # Return one of the workers (or block until one is available)
        # Use a lock in order to be able to limit the number of simultaneously running
        # workers even further (e.g., to one) - useful for example for I/O heavy programs
        with Workers._lock:
            return Workers._available.get()

    @staticmethod
    def release(worker: Worker):
        Workers._available.put(worker)

    @staticmethod
    def _get_user_id(username: str):
        # Run `id` command as user <username> to get uid
        # `sudo -u <username> id` prints, for example, the following:
        # uid=1001(worker01) gid=1001(worker01) groups=1001(worker01)
        user_info = str(os.popen("sudo -u {} id 2>/dev/null".format(username)).read())
        if len(user_info.split()) < 1:
            return -1
        return int(user_info.split()[0].split("=")[1].split("(")[0])

    @staticmethod
    def _create_user(username: str):
        if os.system("useradd --shell /bin/bash -G workers -M {}".format(username)):
            logger.fatal("Cannot create user '{}'!".format(username))
            exit(-1)
        return Workers._get_user_id(username)

    @staticmethod
    def _umount_and_delete_recursive(path, base_dir=False):
        for child in os.listdir(path):
            if base_dir and child == "home":
                continue
            child_path = os.path.join(path, child)

            # Un-mount it if it is one of the mounted directories
            if common.is_mount(child_path):
                # logger.info("    -- unmounting directory {}".format(child_path))
                umount_successful = False
                for _ in range(3):
                    if os.system("sudo umount --recursive {}".format(child_path)) == 0:
                        umount_successful = True
                        break
                    sleep(0.1)
                if not umount_successful:
                    logger.fatal("Could not umount directory {}!".format(child_path))
                    exit(-1)

            # Now it should be a simple file or directory. Delete it recursively.
            if os.path.isdir(child_path):
                Workers._umount_and_delete_recursive(child_path, False)
                try:
                    os.rmdir(child_path)
                except OSError:
                    logger.warning("    -- skipping '{}' (directory not empty)".format(child_path))
            else:
                logger.warning("    -- skipping '{}' (not a directory)".format(child_path))

    @staticmethod
    def _clean_worker_dir(worker_path):
        """
        Delete the sandbox/workerXX/ directory recursively unmounting paths where needed
        """
        # Unmount and remove mounted directories
        Workers._umount_and_delete_recursive(path=worker_path, base_dir=True)

        # Delete recursively custom directories
        if os.path.exists(os.path.join(worker_path, "home")):
            shutil.rmtree(os.path.join(worker_path, "home"))

        # Finally remove the entire worker directory (should be empty now)
        # This can be done in the recursion above, but we want an extra check that the cleanup succeeded.
        try:
            # logger.info("    -- deleting base directory...")
            os.rmdir(worker_path)
        except OSError:
            logger.error("Worker directory '{}' not empty after cleanup!".format(worker_path))

    @staticmethod
    def _clean_workers():
        with Workers._lock:
            for i in range(config.MAX_PARALLEL_WORKERS):
                worker = Worker(cpu=-1, user=-1, name="worker{:02d}".format(i + 1))
                logger.info("Cleaning up {}...".format(worker.name))

                # Clean the worker's directory (sandbox/workerXX) if it exists
                if os.path.exists(worker.path):
                    logger.info("  >> removing sandbox directory...")
                    Workers._clean_worker_dir(worker.path)

                # Delete the user
                if os.system("deluser {}".format(worker.name)) == 0:
                    logger.info("  >> removed system user {}".format(worker.name))

                logger.info("  >> completed!")
        if os.system("delgroup workers") == 0:
            logger.info("  >> removed system group workers")

    @staticmethod
    def _create_worker_dir(worker_path):
        # Create the user's root directory
        os.mkdir(worker_path, 0o755)

        # Prepare the directory for chroot-ing by mounting vital system directories
        logger.info("  >> mounting system paths...")

        for mount_dir in ["bin", "lib", "lib64", "usr", "dev", "sys", "proc", "etc"]:
            mount_source = "/{}".format(mount_dir)
            mount_destination = os.path.join(worker_path, mount_dir)
            if not os.path.exists(mount_destination):
                os.makedirs(mount_destination, 0o755)
            if os.system("sudo mount --rbind {} {}".format(mount_source, mount_destination)) != 0:
                logger.fatal("Could not mount '{}'!".format(mount_source))
                exit(-1)
            if os.system("sudo mount --make-rslave {}".format(mount_destination)) != 0:
                logger.fatal("Could not mount as rslave '{}'!".format(mount_source))
                exit(-1)

        # Unmount the sudo command
        os.system("sudo umount /usr/bin/sudo")

        # Create a /home directory in which all user files are copied to
        # This is also the working directory in which commands are executed
        logger.info("  >> creating home directory...")
        os.mkdir(os.path.join(worker_path, "home"), 0o755)

    @staticmethod
    def _setup_workers():
        with Workers._lock:
            # Skip if already done
            if Workers._have_been_set_up:
                return

            # Check if MAX_PARALLEL_WORKERS is set properly
            if config.MAX_PARALLEL_WORKERS > psutil.cpu_count(logical=True):
                logger.fatal(
                    "MAX_PARALLEL_WORKERS set to {}, but max CPU threads are {}.".format(
                        config.MAX_PARALLEL_WORKERS, psutil.cpu_count(logical=True)
                    )
                )
                exit(-1)
            if config.MAX_PARALLEL_WORKERS > psutil.cpu_count(logical=False):
                logger.warning(
                    "MAX_PARALLEL_WORKERS set to {}, but physical CPU cores are {}.".format(
                        config.MAX_PARALLEL_WORKERS, psutil.cpu_count(logical=False)
                    )
                )

            # Limit the single core memory bandwidth and L3 cache access (new Intel Xeon processors only)
            # If this prints any errors, see the info on top of limiter.py or comment the line below
            limiter.set_rdt_limits()

            # Create a system group for managing sudo and other permissions if not already present
            # We only care about the exit code of the grep, not the actual result, so disregard it.
            if os.system("sudo cat /etc/group | grep workers > /dev/null") != 0:
                if os.system("sudo groupadd workers") != 0:
                    logger.fatal('Could not create system group "workers"!')
                    exit(-1)

            # Create the user and sandbox (chroot) directory for each worker
            for i in range(config.MAX_PARALLEL_WORKERS):
                worker = Worker(cpu=-1, user=-1, name="worker{:02d}".format(i + 1))
                logger.info("Setting up {}...".format(worker.name))

                # Check if a UNIX user with this username exists and create one if not
                worker.user = Workers._get_user_id(worker.name)
                if worker.user == -1:
                    logger.info("  >> creating user...")
                    worker.user = Workers._create_user(worker.name)

                # Clean the worker's sandbox directory (sandbox/workerXX) if it exists
                if os.path.exists(worker.path):
                    logger.info("  >> cleaning sandbox directory...")
                    Workers._clean_worker_dir(worker.path)

                # Create the worker's sandbox directory (sandbox/workerXX) and prepare it for chrooting
                logger.info("  >> creating sandbox directory...")
                Workers._create_worker_dir(worker.path)

                logger.info("  >> completed!")

            logger.info("Workers set up successfully.")
            Workers._have_been_set_up = True

    @staticmethod
    def _queue_workers():
        with Workers._lock:
            # Skip if already done
            if Workers._have_been_queued:
                return

            # In order to avoid workers set on the same physical core (due to hyper-threading) and
            # to distribute them better on different physical CPUs (in systems with more than one),
            # set the affinity of each worker appropriately.
            affinity = list(range(0, psutil.cpu_count()))

            # Create a queue which allows at most MAX_PARALLEL_WORKERS workers
            Workers._available = Queue(maxsize=config.MAX_PARALLEL_WORKERS)
            for i in range(config.MAX_PARALLEL_WORKERS):
                name = "worker{:02d}".format(i + 1)
                Workers._available.put(
                    Worker(
                        cpu=affinity[i % len(affinity)], user=Workers._get_user_id(name), name=name
                    )
                )

            logger.info("Workers queued successfully.")
            Workers._have_been_queued = True
