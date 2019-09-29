#
# Miscellaneous functions
#
import os
import requests

import config
import logging
import logging.handlers
from concurrent.futures import ThreadPoolExecutor
from queue import Queue


class RunResult:
    def __init__(self, status, error_message="", exit_code=0, exec_time=0.0, exec_memory=0.0, score=0.0, info="", output=""):
        self.status = status
        self.error_message = error_message
        self.exit_code = exit_code
        self.exec_time = exec_time
        self.exec_memory = exec_memory
        self.score = score
        self.info = info
        self.output = output


# Use static scheduler and executor
scheduler = ThreadPoolExecutor(max_workers=config.WORKER_COUNT)
executor = ThreadPoolExecutor(max_workers=config.WORKER_COUNT)
containers = Queue(maxsize=config.WORKER_COUNT)


def get_logger(name):
    fmt = "%(asctime)s | %(name)s | %(levelname)s | %(message)s"
    formatter = logging.Formatter(fmt=fmt)

    log_file_path = os.path.abspath(config.PATH_LOG_FILE)
    if not os.path.exists(os.path.dirname(log_file_path)):
        # Create the directory
        os.mkdir(os.path.dirname(log_file_path))

        # Create and chmod the file
        open(log_file_path, "w").close()
        os.chmod(log_file_path, 0o0664)

    file_output = logging.handlers.RotatingFileHandler(
        filename=log_file_path, encoding="utf-8", maxBytes=1048576, backupCount=5)
    file_output.setLevel(logging.INFO)
    file_output.setFormatter(formatter)

    console_output = logging.StreamHandler()
    console_output.setLevel(logging.INFO)
    console_output.setFormatter(formatter)

    ret_logger = logging.getLogger(name)
    ret_logger.setLevel(logging.INFO)
    ret_logger.addHandler(file_output)
    ret_logger.addHandler(console_output)
    return ret_logger


logger = get_logger(__name__)


def get_language_by_exec_name(executable):
    if executable.endswith(config.EXECUTABLE_EXTENSION_CPP):
        return "C++"
    elif executable.endswith(config.EXECUTABLE_EXTENSION_JAVA):
        return "Java"
    elif executable.endswith(config.EXECUTABLE_EXTENSION_PYTHON):
        return "Python"
    else:
        logger.error("Could not determine language by executable name: '{}'!".format(executable))
        return "Unknown"


def get_source_extension(language):
    if language == "C++":
        return config.SOURCE_EXTENSION_CPP
    elif language == "Java":
        return config.SOURCE_EXTENSION_JAVA
    elif language == "Python":
        return config.SOURCE_EXTENSION_PYTHON
    else:
        logger.error("Requested source extension for unknown language: '{}'!".format(language))
        return ".unk"


def get_executable_extension(language):
    if language == "C++":
        return config.EXECUTABLE_EXTENSION_CPP
    elif language == "Java":
        return config.EXECUTABLE_EXTENSION_JAVA
    elif language == "Python":
        return config.EXECUTABLE_EXTENSION_PYTHON
    else:
        logger.error("Requested executable extension for unknown language: '{}'!".format(language))
        return ".unk"


def get_time_offset(language):
    if language == config.LANGUAGE_CPP:
        return config.TIME_OFFSET_CPP
    if language == config.LANGUAGE_JAVA:
        return config.TIME_OFFSET_JAVA
    if language == config.LANGUAGE_PYTHON:
        return config.TIME_OFFSET_PYTHON
    raise Exception("Unsupported language")


def get_memory_offset(language):
    if language == config.LANGUAGE_CPP:
        return config.MEMORY_OFFSET_CPP
    if language == config.LANGUAGE_JAVA:
        return config.MEMORY_OFFSET_JAVA
    if language == config.LANGUAGE_PYTHON:
        return config.MEMORY_OFFSET_PYTHON
    raise Exception("Unsupported language")


def send_request(method, url, data=None):
    username = config.AUTH_USERNAME
    password = config.AUTH_PASSWORD

    response = None
    try:
        if method == "GET":
            response = requests.get(url, data, auth=(username, password), stream=True)
        elif method == "POST":
            response = requests.post(url, data, auth=(username, password))
        else:
            logger.error("Could not send request: unsupported request method '{}'!".format(method))
        if response.status_code != requests.codes.ok:
            logger.error("Could not complete request to {}: got response code {}!".format(url, response.status_code))
    except (requests.exceptions.ConnectionError, requests.exceptions.Timeout, requests.exceptions.HTTPError) as ex:
        logger.error("Could not complete request to {}: got exception {}".format(url, ex))

    return response


def download_file(url, destination):
    response = send_request("GET", url)
    if response.status_code != 200:
        raise RuntimeError("Got response code different than 200!")
    try:
        with open(destination, "wb") as file:
            # Write 1MB chunks from the file at a time
            for chunk in response.iter_content(config.FILE_DOWNLOAD_CHUNK_SIZE):
                file.write(chunk)
    except EnvironmentError:
        raise RuntimeError("Could not write downloaded data to file!")


