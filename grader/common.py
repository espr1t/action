#
# Miscellaneous functions
#
import requests

import config
import logging
from functools import wraps
from flask import jsonify, request, make_response
from concurrent.futures import ThreadPoolExecutor
from hashlib import sha1


class RunResult:
    def __init__(self, status, error_message, exit_code, exec_time, exec_memory, score, info):
        self.status = status
        self.error_message = error_message
        self.exit_code = exit_code
        self.exec_time = exec_time
        self.exec_memory = exec_memory
        self.score = score
        self.info = info


# Use static scheduler and executor
scheduler = ThreadPoolExecutor(max_workers=config.WORKER_COUNT)
executor = ThreadPoolExecutor(max_workers=config.WORKER_COUNT)


def get_language_by_exec_name(executable):
    if executable.endswith(config.EXECUTABLE_EXTENSION_CPP):
        return "C++"
    elif executable.endswith(config.EXECUTABLE_EXTENSION_JAVA):
        return "Java"
    elif executable.endswith(config.EXECUTABLE_EXTENSION_PYTHON):
        return "Python"
    else:
        logger = logging.getLogger("commn")
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
        logger = logging.getLogger("commn")
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
        logger = logging.getLogger("commn")
        logger.error("Requested executable extension for unknown language: '{}'!".format(language))
        return ".unk"


def create_response(status, message, data=None):
    if data is None:
        data = {}
    data["message"] = message
    return make_response(jsonify(**data), status, {"Content-Type": "application/json"})


def send_request(method, url, data=None):
    username = config.AUTH_USERNAME
    password = config.AUTH_PASSWORD
    logger = logging.getLogger("commn")

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


def hashed_auth_token(token):
    return sha1(token.encode("utf-8")).hexdigest()


def authorized(auth):
    if auth is None or auth.username is None or auth.password is None:
        return False
    if auth.username != hashed_auth_token(config.AUTH_USERNAME):
        return False
    if auth.password != hashed_auth_token(config.AUTH_PASSWORD):
        return False
    return True


def requires_auth(fun):
    @wraps(fun)
    def decorated(*args, **kwargs):
        if not authorized(request.authorization):
            logging.warning("Unauthorized user trying to access " + fun.__name__ + "()!")
            return create_response(401, "Please use authentication.")
        return fun(*args, **kwargs)
    return decorated
