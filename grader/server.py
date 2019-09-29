"""
Grader entry point. Start the Flask server here.

The following API is exposed:
    /available                      For checking whether the Grader is available
    /evaluate                       For evaluation of a submission
    /print-pdf                      For printing a page to a PDF file
"""
import os
import json
import flask
from time import sleep

import config
import common
import network
from evaluator import Evaluator
from printer import Printer
from executor import Executor


app = flask.Flask("Grader")

logger = common.get_logger(__name__)
# NOTE: Do not remove, this is done to initialize the werkzeug's logger
werkzeug_logger = common.get_logger("werkzeug")


@app.route("/available", methods=["GET"])
@network.requires_auth
def available():
    """ Used for checking whether the Grader is available. """
    return network.create_response(200, "Grader is healthy.")


@app.route("/evaluate", methods=["POST"])
@network.requires_auth
def evaluate():
    """ Used for grading a submission. """
    data = json.loads(flask.request.form["data"])
    try:
        # Sleep for a very short while so werkzeug can print its log BEFORE we start printing from here
        sleep(0.01)
        evaluator = Evaluator(data)
        common.scheduler.submit(evaluator.evaluate)
    except RuntimeError as exception:
        return network.create_response(500, exception)

    return network.create_response(200, "Submission received.")


@app.route("/print-pdf", methods=["POST"])
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


if __name__ == "__main__":
    # Change current working directory to the one the script is in
    os.chdir(os.path.dirname(os.path.abspath(__file__)))

    Executor.setup_containers(config.WORKER_COUNT)

    app.run(host="0.0.0.0", port="5000", debug=True)
