"""
Network miscellaneous functions and wrappers.
"""

import requests
from functools import wraps
from flask import jsonify, request, make_response
from hashlib import sha1

import config
import common

logger = common.get_logger(__file__)


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
            logger.warning("Unauthorized user trying to access " + fun.__name__ + "()!")
            return create_response(401, "Please use authentication.")
        return fun(*args, **kwargs)
    return decorated


def create_response(status, message, data=None):
    # NOTE: Do not change the default data= to {}, as it is mutable
    # and Python will reuse the same object each time this is called!
    if data is None:
        data = {}
    data["message"] = message
    return make_response(jsonify(**data), status, {"Content-Type": "application/json"})


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
