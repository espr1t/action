#
# Grader entry point. Start the Flask server here.
#
# The following API is exposed:
#   / => For debug purposes only
#   /healthcheck => For checking whether the Grader is available.
#

import flask
app = flask.Flask(__name__)


#
# Useless, added for debugging purposes only
#
@app.route("/")
def main_page():
    response = {
        "status": 200,
        "message": "Grader root directory."
    }
    return flask.jsonify(**response)


#
# Used for checking whether the Grader is available.
# Returns a response with status 200 if the service is healthy.
#
@app.route("/healthcheck", methods=['GET', 'POST'])
def health_check():
    response = {
        "status": 200,
        "message": "healthy"
    }
    return flask.jsonify(**response)

if __name__ == "__main__":
    app.run(debug=True)
