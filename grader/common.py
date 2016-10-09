#
# Miscellaneous functions
#
import config
import logging
from functools import wraps
from flask import jsonify, request, make_response
from concurrent.futures import ThreadPoolExecutor

# Use static scheduler and executor
scheduler = ThreadPoolExecutor(config.WORKER_COUNT)
executor = ThreadPoolExecutor(config.WORKER_COUNT)


def create_response(status, message, data=None):
    if data is None:
        data = {}
    data["message"] = message
    return make_response(jsonify(**data), status, {"Content-Type": "application/json"})


def authorized(auth):
    if auth is None or auth.username is None or auth.password is None:
        return False
    return auth.username == config.AUTH_USERNAME and auth.password == config.AUTH_PASSWORD


def requires_auth(fun):
    @wraps(fun)
    def decorated(*args, **kwargs):
        if not authorized(request.authorization):
            logging.warning("Unauthorized user trying to access " + fun.__name__ + "()!")
            return create_response(401, "Please use authentication.")
        return fun(*args, **kwargs)
    return decorated
