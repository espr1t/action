"""
Grader entry point. Start the Flask server here.

The following API is exposed:
    /available                      For checking whether the Grader is available
    /evaluate                       For evaluation of a submission
    /print-pdf                      For printing a page to a PDF file
    /replay                         For getting a game replay log
"""
import os
import json
import flask
from time import sleep

import common
import config
import network
import initializer
from threading import Lock
from evaluator import Evaluator
from printer import Printer

app = flask.Flask("Grader")
lock = Lock()

logger = common.get_logger(__file__)
# NOTE: Do not remove, this is done to initialize the werkzeug's logger
werkzeug_logger = common.get_logger("werkzeug")


@app.route("/available", methods=["GET"])
@network.requires_auth
def available():
    """ Used for checking whether the Grader is available. """
    return network.create_response(200, "Grader is healthy.")


@app.route("/recent", methods=["GET"])
@network.requires_auth
def recent():
    """ Used for getting the grader's queue (last up to 100 entries). """
    return network.create_response(status=200, message="", data={"submits": common.recent_submits})


@app.route("/evaluate", methods=["POST"])
@network.requires_auth
def evaluate():
    global lock
    """ Used for grading a submission. """
    data = json.loads(flask.request.form["data"])
    try:
        with lock:
            # Sleep for a very short while so werkzeug can print its log BEFORE we start printing from here
            sleep(0.01)
            evaluator = Evaluator(data)
            common.recent_submits.append(data["key"])
            if len(common.recent_submits) > 100:
                common.recent_submits = common.recent_submits[-100:]
            common.request_pool.submit(evaluator.evaluate)
    except RuntimeError as exception:
        return network.create_response(500, exception)

    return network.create_response(200, "Submission received.")


@app.route("/print", methods=["POST"])
@network.requires_auth
def print_pdf():
    """ Used for printing a document to PDF """
    data = json.loads(flask.request.form["data"])
    logger.info("Printing PDF from URL: {}".format(data["url"]))

    try:
        pdf_path = Printer.get_pdf(data["url"])
        if pdf_path is None:
            logger.error("Could not create PDF for URL: {}".format(data["url"]))
            return network.create_response(404, "File cannot be printed to pdf.")
        return flask.send_from_directory(os.path.dirname(pdf_path), os.path.basename(pdf_path))
    except RuntimeError as exception:
        logger.error("Runtime Error while trying to create a PDF for URL: {}".format(data["url"]))
        return network.create_response(500, exception)


@app.route("/replay", methods=["POST"])
@network.requires_auth
def replay():
    """ Used for returning a replay log to the front-end """
    data = json.loads(flask.request.form["data"])
    logger.info("Getting replay: {}".format(data["key"]))

    path = os.path.abspath(os.path.join(config.PATH_REPLAYS, data["key"]))
    if not os.path.isfile(path):
        logger.error("No replay with ID {}.".format(data["key"]))
        return network.create_response(404, "No replay with ID {}.".format(data["key"]))
    return flask.send_from_directory(os.path.dirname(path), os.path.basename(path))


if __name__ == "__main__":
    initializer.init()
    app.run(host="0.0.0.0", port="5000", debug=True)
