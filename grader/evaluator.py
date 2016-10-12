#
# Handles grading of a submission.
# The grading consists the following steps:
#     1. The code is being compiled. An error is returned if there is a compilation error.
#     2. The specified tests are being downloaded (if not already present).
#     3. The solution is being executed against each test case in a sandbox.
# Updates the frontend after each test case, but no more often than 0.5 sec (with the last test being an exception).
#
import logging

from os import path, makedirs
import config
import shutil
from compiler import Compiler
from common import executor, send_request
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

        # Configure logger
        self.logger = logging.getLogger("Evaluator")
        self.logger.setLevel(logging.INFO)
        formatter = logging.Formatter(
                "%(levelname)s %(asctime)s (submission {}): %(message)s".format(self.id), "%Y-%m-%dT%H:%M:%S")
        console_handler = logging.StreamHandler()
        console_handler.setLevel(logging.INFO)
        console_handler.setFormatter(formatter)
        self.logger.addHandler(console_handler)
        self.logger.propagate = False

        def get_source_extension(self):
        return ".cpp" if self.language == "C++" else ".java" if self.language == "Java" else ".py"

    def get_executable_extension(self):
        return ".o" if self.language == "C++" else ".class" if self.language == "Java" else ".py"

    def evaluate(self):
        # Send an update that preparation has been started for executing this submission
        self.logger.info("Evaluating submission {}".format(self.id))
        self.send_update(Progress.PREPARING)

        # Create sandbox directory
        self.logger.info("  >> creating sandbox directory...")
        status = self.create_sandbox_dir()
        if status != "":
            results = self.fill_results(TestStatus.INTERNAL_ERROR)
            self.send_update(Progress.FINISHED, status, results)
            return

        # Download the test files (if not downloaded already)
        self.logger.info("  >> downloading test files...")
        if self.download_tests() != "":
            return

        # Save the source to a file so we can compile it later
        self.logger.info("  >> writing source code to file...")
        write_source_status = self.write_source()
        if write_source_status != "":
            results = self.fill_results(TestStatus.INTERNAL_ERROR)
            self.send_update(Progress.FINISHED, write_source_status, results)
            return

        # Send an update that the compilation has been started for this submission
        self.send_update(Progress.COMPILING)

        # Compile
        self.logger.info("  >> compiling...")
        compile_status = self.compile()
        if compile_status != "":
            self.logger.info("    -- compilation error!")
            results = self.fill_results(TestStatus.COMPILATION_ERROR)
            self.send_update(Progress.FINISHED, compile_status, results)
            return

        # Clean up remaining files
        self.logger.info("  >> cleaning up...")
        self.cleanup()

    def send_update(self, status, message="", results=None):
        self.logger.info("  >> sending update with message = {}".format(message))
        data = {
            "id": self.id,
            "status": status.value,
            "message": message
        }
        if results is not None:
            data["results"] = results

        print(data)
        send_request("post", self.update_url, data)

    def fill_results(self, status):
        results = []
        for test in self.tests:
            results[test["position"]] = status
        return results

    def create_sandbox_dir(self):
        status = ""
        try:
            # Delete if already present (maybe regrade?)
            if path.exists(self.path_sandbox):
                shutil.rmtree(self.path_sandbox)
            # Create the submit testing directory
            makedirs(self.path_sandbox)
        except OSError as ex:
            status = str(ex)
            self.logger.error(ex)
        return status

    def download_test(self, test_name, test_hash):
        test_path = config.PATH_TESTS + test_hash

        # Check if file already exists
        if path.exists(test_path):
            return

        # If not, we should download it
        url = self.tests_url + test_name
        self.logger.info("Downloading file {} with hash {} from URL: {}".format(test_name, test_hash, url))
        response = send_request("get", url)
        if response.status_code != 200:
            self.logger.error("Could not download test {} with hash {} using URL: {}".format(test_name, test_hash, url))
            raise Exception("Could not download test file!")
        with open(test_path, "wb") as file:
            # Write 1MB chunks from the file at a time
            for chunk in response.iter_content(config.FILE_DOWNLOAD_CHUNK_SIZE):
                file.write(chunk)

    def download_tests(self):
        status = ""
        try:
            for test in self.tests:
                self.download_test(test["inpFile"], test["inpHash"])
                self.download_test(test["solFile"], test["solHash"])
        except Exception as ex:
            status = str(ex)
            self.logger.error(ex)
        return status

    def write_source(self):
        status = ""
        try:
            with open(self.path_source, "w") as file:
                file.write(self.source)
        except OSError as ex:
            status = "Internal error: " + str(ex)
            self.logger.error(ex)
        return status

    def compile(self):
        try:
            status = executor.submit(Compiler.compile, self.path_source, self.language, self.path_executable).result()
        except ValueError as ex:
            # If a non-compiler error occurred, log the message in addition to sending it to the user
            status = "Internal error: " + str(ex)
            self.logger.error(ex)
        return status

    def cleanup(self):
        self.logger.info("Cleaning up sandbox of submission {id}".format(id=self.id))
        shutil.rmtree(self.path_sandbox)
