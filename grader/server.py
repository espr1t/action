#
# Grader entry point. Start the Flask server here.
#
# The following API is exposed:
#   / => For debug purposes only
#   /healthcheck => For checking whether the Grader is available.
#
from flask import Flask
from common import requires_auth, create_response

app = Flask(__name__)


#
# Useless, added for debugging purposes only
#
@app.route("/")
def main_page():
    return create_response(200, "Grader root directory.")


#
# Used for checking whether the Grader is available.
# Returns a response with status 200 if the service is healthy.
#
@app.route("/healthcheck", methods=['GET', 'POST'])
@requires_auth
def health_check():
    return create_response(200, "Grader is healthy.")

if __name__ == "__main__":
    app.run(debug=True)
