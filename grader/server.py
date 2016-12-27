"""
Grader entry point. Start the Flask server here.

The following API is exposed:
    /available                      For checking whether the Grader is available
    /evaluate                       For evaluation of a submission
"""
from flask import Flask, request
from common import scheduler, requires_auth, create_response
from evaluator import Evaluator
import json
from pprint import PrettyPrinter

app = Flask(__name__)


def print_data(data):
    pp = PrettyPrinter(indent=4)
    pp.pprint(data)
    print(json.dumps(data, ensure_ascii=False))


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
    # print_data(data)
    try:
        evaluator = Evaluator(data)
        scheduler.submit(evaluator.evaluate)
    except RuntimeError as exception:
        return create_response(500, exception)

    return create_response(200, "Submission received.")

if __name__ == "__main__":
    app.run(host='0.0.0.0', debug=True)
