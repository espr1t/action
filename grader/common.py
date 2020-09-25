"""
Miscellaneous functions and classes
"""

import os
import logging
from enum import Enum
from dataclasses import dataclass, field
from concurrent.futures import ThreadPoolExecutor

import config

# Use static thread pools for processing requests and jobs
recent_submits = []
request_pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_SUBMITS)
job_pool = ThreadPoolExecutor(max_workers=config.MAX_PARALLEL_SUBMITS)


@dataclass
class TestInfo:
    inpFile: str
    inpHash: str
    inpPath: str = field(init=False)
    solFile: str
    solHash: str
    solPath: str = field(init=False)
    position: int

    def __post_init__(self):
        # Set paths for input and solution
        self.inpPath = os.path.join(config.PATH_TESTS, self.inpHash)
        self.solPath = os.path.join(config.PATH_TESTS, self.solHash)


class TestStatus(Enum):
    """ Defines variable results (or progress) of running the tests. """
    PREPARING = 0
    COMPILING = 1
    TESTING = 2
    COMPILATION_ERROR = 3
    WRONG_ANSWER = 4
    RUNTIME_ERROR = 5
    TIME_LIMIT = 6
    MEMORY_LIMIT = 7
    INTERNAL_ERROR = 8
    ACCEPTED = 9
    UNKNOWN = 10


def get_logger(name):
    name = name.split('/')[-1].split('.')[0]

    if not os.path.exists(os.path.dirname(config.PATH_LOG_FILE)):
        # Create the directory
        os.mkdir(os.path.dirname(config.PATH_LOG_FILE))

        # Create and chmod the file
        open(config.PATH_LOG_FILE, "w").close()
        os.chmod(config.PATH_LOG_FILE, 0o664)

    formatter = logging.Formatter(fmt="%(asctime)s | %(name)s | %(levelname)s | %(message)s")
    logging_level = logging.INFO if "RUNNING_TESTS" not in os.environ else logging.FATAL

    file_output = logging.FileHandler(filename=config.PATH_LOG_FILE)
    file_output.setLevel(logging_level)
    file_output.setFormatter(formatter)

    console_output = logging.StreamHandler()
    console_output.setLevel(logging_level)
    console_output.setFormatter(formatter)

    ret_logger = logging.getLogger(name)
    ret_logger.setLevel(logging_level)
    ret_logger.handlers = []
    ret_logger.propagate = False
    ret_logger.addHandler(file_output)
    ret_logger.addHandler(console_output)

    return ret_logger


logger = get_logger(__file__)


def is_mount(path):
    return os.system("sudo mountpoint {} 1>/dev/null".format(path)) == 0


def get_source_extension(language):
    if language == config.LANGUAGE_CPP:
        return config.SOURCE_EXTENSION_CPP
    elif language == config.LANGUAGE_JAVA:
        return config.SOURCE_EXTENSION_JAVA
    elif language == config.LANGUAGE_PYTHON:
        return config.SOURCE_EXTENSION_PYTHON
    else:
        logger.error("Requested source extension for unknown language: '{}'!".format(language))
        return ".unk"


def get_executable_extension(language):
    if language == config.LANGUAGE_CPP:
        return config.EXECUTABLE_EXTENSION_CPP
    elif language == config.LANGUAGE_JAVA:
        return config.EXECUTABLE_EXTENSION_JAVA
    elif language == config.LANGUAGE_PYTHON:
        return config.EXECUTABLE_EXTENSION_PYTHON
    else:
        logger.error("Requested executable extension for unknown language: '{}'!".format(language))
        return ".unknown"


def get_language_by_exec_name(executable_name):
    if executable_name.endswith(config.EXECUTABLE_EXTENSION_CPP):
        return config.LANGUAGE_CPP
    elif executable_name.endswith(config.EXECUTABLE_EXTENSION_JAVA):
        return config.LANGUAGE_JAVA
    elif executable_name.endswith(config.EXECUTABLE_EXTENSION_PYTHON):
        return config.LANGUAGE_PYTHON
    raise Exception("Could not determine language for executable '{}'!".format(executable_name))


def get_language_by_source_name(source_name):
    if source_name.endswith(config.SOURCE_EXTENSION_CPP):
        return config.LANGUAGE_CPP
    elif source_name.endswith(config.SOURCE_EXTENSION_JAVA):
        return config.LANGUAGE_JAVA
    elif source_name.endswith(config.SOURCE_EXTENSION_PYTHON):
        return config.LANGUAGE_PYTHON
    raise Exception("Could not determine language for executable '{}'!".format(source_name))


def get_time_offset(language):
    if language == config.LANGUAGE_CPP:
        return config.TIME_OFFSET_CPP
    if language == config.LANGUAGE_JAVA:
        return config.TIME_OFFSET_JAVA
    if language == config.LANGUAGE_PYTHON:
        return config.TIME_OFFSET_PYTHON
    raise Exception("Unsupported language '{}'!".format(language))


def get_memory_offset(language):
    if language == config.LANGUAGE_CPP:
        return config.MEMORY_OFFSET_CPP
    if language == config.LANGUAGE_JAVA:
        return config.MEMORY_OFFSET_JAVA
    if language == config.LANGUAGE_PYTHON:
        return config.MEMORY_OFFSET_PYTHON
    raise Exception("Unsupported language '{}'!".format(language))
