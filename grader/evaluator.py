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
from time import perf_counter, sleep, time
import config
import shutil
from compiler import Compiler
from common import executor, send_request, download_file
from status import TestStatus
from runner import Runner
from threading import Thread


class Evaluator:
    def __init__(self, data):
        # Sleep for a very short while so werkzeug can print its log BEFORE we start printing from here
        sleep(0.01)

        # Update Timer
        self.update_timer = -1
        self.update_message = ""
        self.update_results = []

        # Server endpoints
        self.update_url = data["updateEndpoint"]
        self.tests_url = data["testsEndpoint"]
        self.checker_url = data["checkerEndpoint"]

        # Submit information
        self.id = data["id"]
        self.source = data["source"]
        self.language = data["language"]
        self.time_limit = data["timeLimit"]
        self.memory_limit = data["memoryLimit"] * 1048576  # Given in MiB, convert to bytes
        self.tests = data["tests"]
        self.checker = data["checker"] if "checker" in data else ""

        # Path to sandbox and files inside
        self.path_sandbox = config.PATH_SANDBOX + "submit_{:06d}/".format(self.id)
        self.path_source = self.path_sandbox + config.SOURCE_NAME + self.get_source_extension()
        self.path_executable = self.path_sandbox + config.EXECUTABLE_NAME + self.get_executable_extension()

        # Configure logger
        self.logger = logging.getLogger("evltr")

    def __del__(self):
        # Clean up remaining files
        self.cleanup()

    def get_source_extension(self):
        return ".cpp" if self.language == "C++" else ".java" if self.language == "Java" else ".py"

    def get_executable_extension(self):
        return ".o" if self.language == "C++" else ".jar" if self.language == "Java" else ".py"

    def evaluate(self):
        # Send an update that preparation has been started for executing this submission
        self.logger.info("[Submission {}] Evaluating submission {}".format(self.id, self.id))
        self.update_frontend("", self.set_results(TestStatus.PREPARING))

        # Create sandbox directory
        self.logger.info("[Submission {}]   >> creating sandbox directory...".format(self.id))
        create_sandbox_status = self.create_sandbox_dir()
        if create_sandbox_status != "":
            self.logger.error("[Submission {}] Could not create sandbox directory. Aborting...".format(self.id))
            self.update_frontend(create_sandbox_status, self.set_results(TestStatus.INTERNAL_ERROR))
            return

        # Download the test files (if not downloaded already)
        self.logger.info("[Submission {}]   >> downloading test files...".format(self.id))
        download_tests_status = self.download_tests()
        if download_tests_status != "":
            self.logger.error("[Submission {}] Could not download tests properly. Aborting...".format(self.id))
            self.update_frontend(download_tests_status, self.set_results(TestStatus.INTERNAL_ERROR))
            return

        # Download and compile the checker (if not downloaded already)
        if self.checker != '' and not path.exists(config.PATH_CHECKERS + self.checker):
            self.logger.info("[Submission {}]   >> downloading checker...".format(self.id))
            download_checker_status = self.download_checker()
            if download_checker_status != "":
                self.logger.error("[Submission {}] Could not download checker. Aborting...".format(self.id))
                self.update_frontend(download_checker_status, self.set_results(TestStatus.INTERNAL_ERROR))
                return
            self.logger.info("[Submission {}]   >> compiling checker...".format(self.id))
            compile_checker_status = self.compile_checker()
            if compile_checker_status != "":
                self.logger.error("[Submission {}] Could not compile checker. Aborting...".format(self.id))
                self.update_frontend(compile_checker_status, self.set_results(TestStatus.INTERNAL_ERROR))
                return

        # Save the source to a file so we can compile it later
        self.logger.info("[Submission {}]   >> writing source code to file...".format(self.id))
        write_source_status = self.write_source()
        if write_source_status != "":
            self.logger.error("[Submission {}] Could not write source file. Aborting...".format(self.id))
            self.update_frontend(write_source_status, self.set_results(TestStatus.INTERNAL_ERROR))
            return

        # Send an update that the compilation has been started for this submission
        self.update_frontend("", self.set_results(TestStatus.COMPILING))

        # Compile
        self.logger.info("[Submission {}]   >> compiling...".format(self.id))
        compile_status = self.compile()
        if compile_status != "":
            self.logger.info("[Submission {}] Could not compile solution. Stopping execution...".format(self.id))
            self.update_frontend(compile_status, self.set_results(TestStatus.COMPILATION_ERROR))
            return

        # Execute each of the tests
        self.logger.info("[Submission {}]   >> starting processing tests...".format(self.id))
        run_status = self.process_tests()
        if run_status != "":
            self.logger.info("[Submission {}] Error while running the solution. Aborting...!".format(self.id))
            self.update_frontend(run_status, self.set_results(TestStatus.INTERNAL_ERROR))
            return

        # Finished with this submission
        self.logger.info("[Submission {}]   >> done with {}!".format(self.id, self.id))
        self.update_frontend("DONE")

    def update_frontend(self, message="", results=None):
        # Merge current message and results with previous ones
        self.update_message = message
        if results is not None:
            for result in results:
                found = False
                for i in range(len(self.update_results)):
                    if self.update_results[i]['position'] == result['position']:
                        self.update_results[i] = result
                        found = True
                        break
                if not found:
                    self.update_results.append(result)

        # Update every UPDATE_INTERVAL seconds so we don't spam the frontend too much
        # We're using time() instead of perf_counter() so we get a UNIX timestamp (with parts of seconds)
        # This info helps figure out WHEN exactly (date + hour) the solution was graded.
        if time() - self.update_timer > config.UPDATE_INTERVAL or self.update_message != "":
            self.update_timer = time()
            data = {
                "id": self.id,
                "message": self.update_message,
                "results": json.dumps(self.update_results),
                "timestamp": self.update_timer
            }
            # Make the updates asynchronous so we don't stop the execution of the tests
            Thread(target=send_request, args=["POST", self.update_url, data]).start()

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
            if not path.exists(self.path_sandbox):
                makedirs(self.path_sandbox)
        except OSError as ex:
            status = str(ex)
            self.logger.error("[Submission {}] {}".format(self.id, str(ex)))
        return status

    def download_test(self, test_name, test_hash):
        test_path = config.PATH_TESTS + test_hash
        # Check if file already exists
        if path.exists(test_path):
            return

        self.logger.info("[Submission {}] Downloading file {} with hash {} from URL: {}".format(
            self.id, test_name, test_hash, self.tests_url + test_name))
        download_file(self.tests_url + test_name, test_path)

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
            self.logger.error("[Submission {}] {}".format(self.id, str(ex)))
        return status

    def download_checker(self):
        checker_source_path = config.PATH_CHECKERS + self.checker + ".cpp"
        # Already downloaded
        if path.exists(checker_source_path):
            return ""

        self.logger.info("[Submission {}] Downloading file {} with hash {} from URL: {}".format(
            self.id, self.checker_url.split('/')[-1], self.checker, self.checker_url))
        try:
            download_file(self.checker_url, checker_source_path)
        except Exception as ex:
            self.logger.error("[Submission {}] Internal Error: {}".format(self.id, str(ex)))
            return "Internal error: " + str(ex)
        return ""

    def compile_checker(self):
        checker_binary_path = config.PATH_CHECKERS + self.checker
        # Already compiled
        if path.exists(checker_binary_path):
            return ""

        checker_source_path = config.PATH_CHECKERS + self.checker + ".cpp"
        try:
            status = executor.submit(Compiler.compile, "C++", checker_source_path, checker_binary_path).result()
        except ValueError as ex:
            # If a non-compiler error occurred, log the message in addition to sending it to the user
            status = "Internal error: " + str(ex)
            self.logger.error("[Submission {}] {}".format(self.id, str(ex)))
        return status

    def write_source(self):
        status = ""
        try:
            with open(self.path_source, "w") as file:
                file.write(self.source)
        except OSError as ex:
            status = "Internal error: " + str(ex)
            self.logger.error("[Submission {}] {}".format(self.id, str(ex)))
        return status

    def compile(self):
        try:
            status = executor.submit(Compiler.compile, self.language, self.path_source, self.path_executable).result()
        except ValueError as ex:
            # If a non-compiler error occurred, log the message in addition to sending it to the user
            status = "Internal error: " + str(ex)
            self.logger.error("[Submission {}] {}".format(self.id, str(ex)))
        return status

    def process_tests(self):
        start_time = perf_counter()
        runner = Runner(self)
        errors = ""

        test_futures = []
        for test in self.tests:
            test_futures.append([test, executor.submit(runner.run, test)])

        for test_future in test_futures:
            test, future = test_future
            try:
                # Wait for the test to be executed
                future.result()
            except ValueError as ex:
                errors += "Internal error on test " + test["inpFile"] + "(" + test["inpHash"] + "): " + str(ex)
                self.logger.error("[Submission {}] {}".format(self.id, str(ex)))
                break
            except Exception as ex:
                self.logger.error("[Submission {}] Got exception: {}".format(self.id, str(ex)))

        self.logger.info("[Submission {}]    -- executed {} tests in {:.3f}s.".format(
            self.id, len(self.tests), perf_counter() - start_time))
        return errors

    def cleanup(self):
        self.logger.info("[Submission {}] Cleaning up sandbox...".format(self.id))
        if path.exists(self.path_sandbox):
            shutil.rmtree(self.path_sandbox)
