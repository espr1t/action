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

# Use static scheduler and executor
scheduler = ThreadPoolExecutor(config.WORKER_COUNT)
executor = ThreadPoolExecutor(config.WORKER_COUNT)


def get_source_extension(language):
    return ".cpp" if language == "C++" else ".java" if language == "Java" else ".py"


def get_executable_extension(language):
    return ".o" if language == "C++" else ".jar" if language == "Java" else ".py"


def create_response(status, message, data=None):
    if data is None:
        data = {}
    data["message"] = message
    return make_response(jsonify(**data), status, {"Content-Type": "application/json"})


def hashed_auth_token(token):
    return sha1(token.encode("utf-8")).hexdigest()


def send_request(method, url, data=None):
    username = config.AUTH_USERNAME
    password = hashed_auth_token(config.AUTH_PASSWORD)
    response = None
    if method == "GET":
        response = requests.get(url, data, auth=(username, password), stream=True)
    elif method == "POST":
        response = requests.post(url, data, auth=(username, password))
    else:
        logger = logging.getLogger("commn")
        logger.error("Could not send request: unsupported request method '{}'!".format(method))
    return response


def download_file(url, destination):
    response = send_request("GET", url)
    if response.status_code != 200:
        logger = logging.getLogger("commn")
        logger.error("Could not download file from URL {}: got response code '{}'!".format(url, response.status_code))
        raise RuntimeError("Got response code different than 200!")

    try:
        with open(destination, "wb") as file:
            # Write 1MB chunks from the file at a time
            for chunk in response.iter_content(config.FILE_DOWNLOAD_CHUNK_SIZE):
                file.write(chunk)
    except EnvironmentError:
        raise RuntimeError("Could not write downloaded data to file!")


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
