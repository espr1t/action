#
# Handles grading of a submission.
# The grading consists the following steps:
#     1. The code is being compiled. An error is returned if there is a compilation error.
#     2. The specified tests are being downloaded (if not already present).
#     3. The solution is being executed against each test case in a sandbox.
# Updates the frontend after each test case, but no more often than 0.5 sec (with the last test being an exception).
#

import config
import requests
from enum import Enum


class Progress(Enum):
    """ Defines variable response types (depending on what happened) """
    PREPARING = "Preparing",
    COMPILING = "Compiling",
    TESTING = "Testing",
    FINISHED = "Finished"


class TestStatus(Enum):
    COMPILATION_ERROR = "Compilation Error",
    WRONG_ANSWER = "Wrong Answer",
    RUNTIME_ERROR = "Runtime Error",
    TIME_LIMIT = "Time Limit",
    MEMORY_LIMIT = "Memory Limit",
    INTERNAL_ERROR = "Internal Error",
    ACCEPTED = "Accepted"


class Evaluator:
    def __init__(self, data):
        # Server endpoints
        self.update_url = data["updateEndpoint"]
        self.tests_url = data["testsEndpoint"]

        # Submit information
        self.id = data["id"]
        self.source = data["source"]
        self.language = data["language"]
        self.checker = data["checker"]
        self.tester = data["tester"]
        self.time_limit = data["timeLimit"]
        self.memory_limit = data["memoryLimit"]
        self.tests = data["tests"]

        # Path to sandbox and files inside
        self.path_sandbox = config.PATH_SANDBOX + "submit_{:06d}/".format(self.id)
        self.path_source = self.path_sandbox + config.SOURCE_NAME + self.get_source_extension()
        self.path_executable = self.path_sandbox + config.EXECUTABLE_NAME + self.get_executable_extension()

    def get_source_extension(self):
        return ".cpp" if self.language == "C++" else ".java" if self.language == "Java" else ".py"

    def get_executable_extension(self):
        return ".o" if self.language == "C++" else ".class" if self.language == "Java" else ".py"

    def evaluate(self):
        # Send an update that preparation has been started for executing this submission
        self.send_update(Progress.PREPARING)

    def send_update(self, status, message="", results=None):
        data = {
            "id": self.id,
            "status": status,
            "message": message
        }
        if results is not None:
            data["results"] = results

        print(data)
        response = requests.post(self.update_url, data, auth=(config.AUTH_USERNAME, config.AUTH_PASSWORD))
        print("Response (" + response.status_code + "): " + response.text)
