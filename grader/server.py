"""
Grader entry point. Start the Flask server here.

The following API is exposed:
    /available                      For checking whether the Grader is available
    /evaluate                       For evaluation of a submission
"""
import os
import json
from flask import Flask, request

from common import scheduler, requires_auth, create_response
from evaluator import Evaluator
from config import PATH_LOG_FILE

app = Flask(__name__)


@app.route("/available", methods=["GET"])
@requires_auth
def available():
    """ Used for checking whether the Grader is available. """
    return create_response(200, "Grader is healthy.")


@app.route("/evaluate", methods=["POST"])
@requires_auth
def evaluate():
    """ Used for grading a submission. """
    data = json.loads(request.form["data"])
    try:
        evaluator = Evaluator(data)
        scheduler.submit(evaluator.evaluate)
    except RuntimeError as exception:
        return create_response(500, exception)

    return create_response(200, "Submission received.")


if __name__ == "__main__":
    # Change current working directory to the one the script is in
    os.chdir(os.path.dirname(os.path.abspath(__file__)))

    if not os.path.exists(os.path.dirname(PATH_LOG_FILE)):
        # Create the directory
        os.mkdir(os.path.dirname(PATH_LOG_FILE))

        # Create and chmod the file
        open(PATH_LOG_FILE, 'w').close()
        os.chmod(PATH_LOG_FILE, 0o0664)

    import logging, logging.config, yaml
    logging.config.dictConfig(yaml.load(open('logging.conf')))

    app.run(host='0.0.0.0', debug=False)
