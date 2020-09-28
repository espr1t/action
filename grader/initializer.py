"""
Sandbox initialization checks if required sandbox directories exist and creates
them if they don't, also mounting important system files within each of them so
that they can be chroot-ed. Finally, it creates users (worker01, worker02, ...)
with limited privileges which are used for the execution within each directory.
"""

import os
import sys

import common
from workers import Workers

logger = common.get_logger(__file__)


def escalate_permissions():
    if os.geteuid() != 0:
        logger.info("Insufficient privileges. Trying to escalate...")
        os.execvp(file="sudo", args=["sudo", "--preserve-env=PATH", "env", "python"] + sys.argv)
        if os.geteuid() != 0:
            logger.error("Insufficient privileges! Please run as root.")
            exit(-1)


def init():
    logger.info("Initializing...")

    # Escalate permissions to root (sudo) if not ran as root
    # This is required for creating the chroot jails and running the solutions.
    escalate_permissions()

    # Set the current working dir to the root of the grader
    # not matter when we run the script from
    root_dir = os.path.dirname(os.path.abspath(__file__))
    if os.getcwd() != root_dir:
        logger.info("Setting working directory to '{}'.".format(root_dir))
        os.chdir(root_dir)

    # Create the sandbox directories for each of the workers and prepare them
    # to be chrooted (mounting /bin, /dev, /lib and similar directories inside)
    Workers.init()

    logger.info("Initialization completed successfully!")


def clean():
    logger.info("Cleaning...")

    # Escalate permissions to root (sudo) if not ran as root
    # This is required for creating the chroot jails and running the solutions.
    escalate_permissions()

    # Clean and delete the sandbox directories for each of the workers
    Workers.clean()


if __name__ == "__main__":
    if len(sys.argv) > 1:
        if sys.argv[1] == "init":
            init()
        elif sys.argv[1] == "clean":
            clean()
        else:
            logger.error("Unknown command {}".format(sys.argv[1]))
    else:
        logger.error("No command given (options: {{\"init\", \"clean\"}})")
