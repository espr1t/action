#
# Handles grading of a submission.
# The grading consists the following steps:
#     1. The code is being compiled. An error is returned if there is a compilation error.
#     2. The specified tests are being downloaded (if not already present).
#     3. The solution is being executed against each test case in a sandbox.
# Updates the frontend after each test case, but no more often than 0.5 sec (with the last test being an exception).
#
import json
import logging

from os import path, makedirs
import config
import shutil
from compiler import Compiler
from common import executor, send_request
from status import TestStatus
from runner import Runner


class Evaluator:
    def __init__(self, data):
        # Server endpoints
        self.update_url = data["updateEndpoint"]
        self.tests_url = data["testsEndpoint"]

        # Submit information
        self.id = data["id"]
        self.source = data["source"]
        self.language = data["language"]
        self.time_limit = data["timeLimit"]
        self.memory_limit = data["memoryLimit"]
        self.tests = data["tests"]
        self.checker = data["checker"] if "checker" in data else ""
        self.tester = data["tester"] if "tester" in data else ""

        # Path to sandbox and files inside
        self.path_sandbox = config.PATH_SANDBOX + "submit_{:06d}/".format(self.id)
        self.path_source = self.path_sandbox + config.SOURCE_NAME + self.get_source_extension()
        self.path_executable = self.path_sandbox + config.EXECUTABLE_NAME + self.get_executable_extension()

        # Configure logger
        self.logger = logging.getLogger("Evaluator")
        self.logger.setLevel(logging.INFO)
        formatter = logging.Formatter(
                "%(levelname)s %(asctime)s (submission {}): %(message)s".format(self.id), "%Y-%m-%dT%H:%M:%S")
        self.handler = logging.StreamHandler()
        self.handler.setLevel(logging.INFO)
        self.handler.setFormatter(formatter)
        self.logger.addHandler(self.handler)
        self.logger.propagate = False

    def __del__(self):
        # Remove log handler
        self.logger.removeHandler(self.handler)

        # Clean up remaining files
        self.logger.info("  >> cleaning up...")
        self.cleanup()

    def get_source_extension(self):
        return ".cpp" if self.language == "C++" else ".java" if self.language == "Java" else ".py"

    def get_executable_extension(self):
        return ".o" if self.language == "C++" else ".class" if self.language == "Java" else ".py"

    def evaluate(self):
        # Send an update that preparation has been started for executing this submission
        self.logger.info("Evaluating submission {}".format(self.id))
        self.send_update(TestStatus.PREPARING.name, self.set_results(TestStatus.PREPARING))

        # Create sandbox directory
        self.logger.info("  >> creating sandbox directory...")
        create_sandbox_status = self.create_sandbox_dir()
        if create_sandbox_status != "":
            self.send_update(create_sandbox_status, self.set_results(TestStatus.INTERNAL_ERROR))
            return

        # Download the test files (if not downloaded already)
        self.logger.info("  >> downloading test files...")
        if self.download_tests() != "":
            return

        # Save the source to a file so we can compile it later
        self.logger.info("  >> writing source code to file...")
        write_source_status = self.write_source()
        if write_source_status != "":
            self.send_update(write_source_status, self.set_results(TestStatus.INTERNAL_ERROR))
            return

        # Send an update that the compilation has been started for this submission
        self.send_update(TestStatus.COMPILING.name, self.set_results(TestStatus.COMPILING))

        # Compile
        self.logger.info("  >> compiling...")
        compile_status = self.compile()
        if compile_status != "":
            self.logger.info("    -- error while compiling the solution!")
            self.send_update(compile_status, self.set_results(TestStatus.COMPILATION_ERROR))
            return

        # Send an update that the testing has been started for this submission
        self.send_update(TestStatus.TESTING.name, self.set_results(TestStatus.TESTING))

        # Execute each of the tests
        self.logger.info("  >> starting processing tests...")
        run_status = self.process_tests()
        if run_status != "":
            self.logger.info("    -- error while running the solution!")
            self.send_update(run_status, self.set_results(TestStatus.INTERNAL_ERROR))
            return

    def send_update(self, message="", results=None):
        self.logger.info("  >> sending update with message = {}".format(message))
        data = {
            "id": self.id,
            "message": message
        }
        if results is not None:
            data["results"] = json.dumps(results)
        send_request("post", self.update_url, data)

    def set_results(self, status):
        results = []
        for test in self.tests:
            results.append({
                "position": test["position"],
                "status": status.name,
                "score": 0
            })
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
        # In case the directory for the tests does not exist, create it
        if not path.exists(config.PATH_DATA):
            makedirs(config.PATH_DATA)
        if not path.exists(config.PATH_TESTS):
            makedirs(config.PATH_TESTS)

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

    def process_tests(self):
        errors = ""
        for test in range(0, len(self.tests)):
            try:
                status = executor.submit(Runner.run, self, test).result()
            except ValueError as ex:
                errors += "Internal error on test " + test["inpHash"] + ": " + str(ex)
                self.logger.error(ex)
                break

            # Record the results for the current test and send update to the frontend
            # TODO: Send batch updates by aggregating several results and send them at least 0.2 seconds apart
            results = [{
                "position": self.tests[test]["position"],
                "status": status.name,
                "score": 1 if status == TestStatus.ACCEPTED else 0
            }]
            self.send_update(TestStatus.TESTING.name, results)
            
        return errors

    def cleanup(self):
        self.logger.info("Cleaning up sandbox of submission {id}".format(id=self.id))
        if path.exists(self.path_sandbox):
            shutil.rmtree(self.path_sandbox)
