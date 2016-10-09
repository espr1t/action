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


def create_response(status, message, data=None):
    if data is None:
        data = {}
    data["message"] = message
    return make_response(jsonify(**data), status, {"Content-Type": "application/json"})


def hashed_auth_token(token):
    return sha1(token.encode("utf-8")).hexdigest()


def send_request(method, url, data=None):
    username = hashed_auth_token(config.AUTH_USERNAME)
    password = hashed_auth_token(config.AUTH_PASSWORD)
    if method == "get":
        response = requests.get(url, data, auth=(username, password), stream=True)
    elif method == "post":
        response = requests.post(url, data, auth=(username, password))
    else:
        logging.error("Unsupported request method '" + method + "'!")
    print("Response (" + response.status_code + "): " + response.text)


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
