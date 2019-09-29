"""
Network miscellaneous functions and wrappers.
"""

from functools import wraps
from flask import jsonify, request, make_response
from hashlib import sha1

import config
import common

logger = common.get_logger(__name__)


def create_response(status, message, data=None):
    # NOTE: Do not change the default data= to {}, as it is mutable
    # and Python will reuse the same object each time this is called!
    if data is None:
        data = {}
    data["message"] = message
    return make_response(jsonify(**data), status, {"Content-Type": "application/json"})


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
