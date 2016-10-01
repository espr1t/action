#
# Miscellaneous functions
#
import logging
from functools import wraps
from flask import jsonify, request, make_response
from config import AUTH_USERNAME, AUTH_PASSWORD


def create_response(status, message, data={}):
    data["message"] = message
    return make_response(jsonify(**data), status, {"Content-Type": "application/json"})


def authorized(auth):
    if auth is None or auth.username is None or auth.password is None:
        return False
    logging.warning("Here with username = {} and password = {}!".format(auth.username, auth.password))
    return auth.username == AUTH_USERNAME and auth.password == AUTH_PASSWORD


def requires_auth(fun):
    @wraps(fun)
    def decorated(*args, **kwargs):
        if not authorized(request.authorization):
            logging.warning("Unauthorized user trying to access " + fun.__name__ + "()!")
            return create_response(401, "Please use authentication.")
        return fun(*args, **kwargs)
    return decorated
