#
# Handles grading of a submission.
# The grading consists the following steps:
#     1. The code is being compiled. An error is returned if there is a compilation error.
#     2. The specified tests are being downloaded (if not already present).
#     3. The solution is being executed against each test case in a sandbox.
# Updates the frontend after each test case, but no more often than 0.5 sec (with the last test being an exception).
#
import logging

import shutil
from os import path, makedirs
from time import perf_counter, sleep

import config
import common
from updater import Updater
from compiler import Compiler
from status import TestStatus
from runner import Runner


class Evaluator:
    def __init__(self, data):
        # Sleep for a very short while so werkzeug can print its log BEFORE we start printing from here
        sleep(0.01)

        # Submit information
        self.id = data["id"]
        self.source = data["source"]
        self.language = data["language"]
        self.time_limit = data["timeLimit"]
        self.memory_limit = data["memoryLimit"] * 1048576  # Given in MiB, convert to bytes

        # Front-end endpoint
        self.update_url = data["updateEndpoint"]

        # List of tests and endpoint where to download them from
        self.tests = data["tests"]
        self.tests_url = data["testsEndpoint"]

        # If a task with checker, there should also be an endpoint where to download it from
        self.checker = data["checker"] if ("checker" in data and data["checker"] != "") else None
        self.checker_url = data["checkerEndpoint"] if "checkerEndpoint" in data else None

        # If a game, there should also be a tester and a list of matches (opponents' names and solutions)
        self.tester = data["tester"] if ("tester" in data and data["tester"] != "") else None
        self.tester_url = data["testerEndpoint"] if "testerEndpoint" in data else None
        self.matches = data["matches"] if "matches" in data else None

        # Whether to use relative or absolute floating point comparison
        self.floats = data["floats"]

        # Path to sandbox and files inside
        self.path_sandbox = config.PATH_SANDBOX + "submit_{:06d}/".format(self.id)
        self.path_source = self.path_sandbox + config.SOURCE_NAME + common.get_source_extension(self.language)
        self.path_executable = self.path_sandbox + config.EXECUTABLE_NAME + common.get_executable_extension(self.language)

        # Frontend server update logic
        self.updater = Updater(self.update_url, self.id, self.tests)

        # Configure logger
        self.logger = logging.getLogger("evltr")

    def __del__(self):
        # Clean up remaining files
        self.cleanup()

    def evaluate(self):
        # Send an update that preparation has been started for executing this submission
        self.logger.info("[Submission {}] Evaluating submission {}".format(self.id, self.id))
        self.updater.add_info("", None, TestStatus.PREPARING)

        # Create sandbox directory
        self.logger.info("[Submission {}]   >> creating sandbox directory...".format(self.id))
        if not self.create_sandbox_dir():
            self.updater.add_info("Error while creating sandbox directory!", None, TestStatus.INTERNAL_ERROR)
            return

        # Download the test files (if not downloaded already)
        self.logger.info("[Submission {}]   >> downloading test files...".format(self.id))
        if not self.download_tests():
            self.updater.add_info("Error while downloading test files!", None, TestStatus.INTERNAL_ERROR)
            return

        # Download and compile the checker (if not already available)
        if self.checker is not None and not path.exists(config.PATH_CHECKERS + self.checker):
            self.logger.info("[Submission {}]   >> updating checker file...".format(self.id))
            if not self.download_and_compile_utility_file(config.PATH_CHECKERS, self.checker, self.checker_url):
                self.updater.add_info("Error while setting up checker!", None, TestStatus.INTERNAL_ERROR)
                return

        # Download and compile the tester (if not already available)
        if self.tester is not None and not path.exists(config.PATH_TESTERS + self.tester):
            self.logger.info("[Submission {}]   >> updating tester file...".format(self.id))
            if not self.download_and_compile_utility_file(config.PATH_TESTERS, self.tester, self.tester_url):
                self.updater.add_info("Error while setting up tester!", None, TestStatus.INTERNAL_ERROR)
                return

        # Save the source to a file so we can compile it later
        self.logger.info("[Submission {}]   >> writing source code to file...".format(self.id))
        if not self.write_source(self.source, self.path_source):
            self.updater.add_info("Error while writing the source to a file!", None, TestStatus.INTERNAL_ERROR)
            return

        # Send an update that the compilation has been started for this submission
        self.updater.add_info("", None, TestStatus.COMPILING)

        # Compile
        self.logger.info("[Submission {}]   >> compiling solution...".format(self.id))
        compilation_status = self.compile(self.language, self.path_source, self.path_executable)
        if compilation_status != "":
            self.logger.info("[Submission {}] Compilation error! Aborting...".format(self.id))
            self.updater.add_info(compilation_status, None, TestStatus.COMPILATION_ERROR)
            return

        # If a standard task, just run the solution on the given tests
        self.logger.info("[Submission {}]   >> starting evaluation of solution...".format(self.id))
        if not self.run_solution():
            self.logger.info("[Submission {}] Error while processing the solution! Aborting...".format(self.id))
            self.updater.add_info("Error while processing the solution!", None, TestStatus.INTERNAL_ERROR)
            return

        # Finished with this submission
        self.logger.info("[Submission {}]   >> done with {}!".format(self.id, self.id))
        self.updater.add_info("DONE", None, None)

    def create_sandbox_dir(self):
        try:
            # Delete if already present (maybe regrade?)
            if path.exists(self.path_sandbox):
                shutil.rmtree(self.path_sandbox)
            # Create the submit testing directory
            if not path.exists(self.path_sandbox):
                makedirs(self.path_sandbox)
        except OSError as ex:
            self.logger.error("[Submission {}] Could not create sandbox directory. Error was: {}".format(self.id, str(ex)))
            return False
        return True

    def download_test(self, test_name, test_hash):
        test_path = config.PATH_TESTS + test_hash
        # Download only if the file doesn't already exist
        if not path.exists(test_path):
            self.logger.info("[Submission {}] Downloading file {} with hash {} from URL: {}".format(
                self.id, test_name, test_hash, self.tests_url + test_name))
            common.download_file(self.tests_url + test_name, test_path)

    def download_tests(self):
        # In case the directory for the tests does not exist, create it
        if not path.exists(config.PATH_DATA):
            makedirs(config.PATH_DATA)
        if not path.exists(config.PATH_TESTS):
            makedirs(config.PATH_TESTS)

        try:
            for test in self.tests:
                self.download_test(test["inpFile"], test["inpHash"])
                self.download_test(test["solFile"], test["solHash"])
        except Exception as ex:
            self.logger.error("[Submission {}] Could not download tests properly. Error was: {}".format(self.id, str(ex)))
            return False
        return True

    def compile(self, language, path_source, path_executable):
        try:
            return common.executor.submit(Compiler.compile, language, path_source, path_executable).result()
        except ValueError as ex:
            # If a non-compiler error occurred, log the message in addition to sending it to the user
            self.logger.error("[Submission {}] Could not compile file {}! Error was: {}".format(
                self.id, path_source, str(ex)))
            return "Internal Error: " + str(ex)

    def compile_utility_file(self, path_source, path_executable):
        # Only compile if not already compiled
        if not path.exists(path_executable):
            self.logger.info("[Submission {}]   >> compiling utility file {}...".format(
                self.id, path.basename(path_source)))
            return self.compile("C++", path_source, path_executable) == ""
        return True

    def download_utility_file(self, url, destination):
        # Only download if not downloaded already
        if not path.exists(destination):
            self.logger.info("[Submission {}]   >> downloading utility file {}".format(self.id, url.split('/')[-1]))
            try:
                common.download_file(url, destination)
            except RuntimeError:
                return False
        return True

    def download_and_compile_utility_file(self, directory, file_hash, url):
        path_source = directory + file_hash + config.SOURCE_EXTENSION_CPP
        path_executable = directory + file_hash + config.EXECUTABLE_EXTENSION_CPP
        if not self.download_utility_file(url, path_source):
            return False
        if not self.compile_utility_file(path_source, path_executable):
            return False
        return True

    def write_source(self, source, destination):
        try:
            with open(destination, "w") as file:
                file.write(source)
        except OSError as ex:
            self.logger.error("[Submission {}] Could not write source file. Error: ".format(self.id, str(ex)))
            return False
        return True

    def process_tests(self):
        start_time = perf_counter()
        runner = Runner(self)
        errors = ""

        test_futures = []
        for result_id in range(len(self.tests)):
            test_futures.append([self.tests[result_id],
                                 common.executor.submit(runner.run, result_id, self.tests[result_id])])

        for test, future in test_futures:
            try:
                # Wait for the test to be executed
                future.result()
            except Exception as ex:
                errors += "Internal error on test " + test["inpFile"] + "(" + test["inpHash"] + "): " + str(ex)
                self.logger.error("[Submission {}] Got exception: {}".format(self.id, str(ex)))
                break

        self.logger.info("[Submission {}]    -- executed {} tests in {:.3f}s.".format(
            self.id, len(self.tests), perf_counter() - start_time))
        return errors

    def process_games(self):
        start_time = perf_counter()
        runner = Runner(self)
        errors = ""

        result_id = 0
        for match in self.matches:
            self.logger.info("[Submission {}]     -- running game {} vs {}...".format(
                self.id, match["player_one_name"], match["player_two_name"]))

            # Get and compile the opponent's solution
            opponent_language = match["language"]
            opponent_path_source = self.path_sandbox + config.OPPONENT_SOURCE_NAME +\
                common.get_source_extension(opponent_language)
            opponent_path_executable = self.path_sandbox + config.OPPONENT_EXECUTABLE_NAME +\
                common.get_executable_extension(opponent_language)

            self.logger.info("[Submission {}]       ++ writing opponent's source...".format(self.id))
            if not self.write_source(match["source"], opponent_path_source):
                self.logger.error("[Submission {}] Could not write opponent's source!".format(self.id))
                continue
            self.logger.info("[Submission {}]       ++ compiling opponent's source...".format(self.id))
            if self.compile(opponent_language, opponent_path_source, opponent_path_executable) != "":
                self.logger.error("[Submission {}] Could not compile opponent's source!".format(self.id))
                continue

            # Run all of the game's tests for this pair of solutions
            test_futures = []
            for test in self.tests:
                # Play forward game
                future = common.executor.submit(runner.play, result_id, test, self.tester,
                        match["player_one_id"], match["player_one_name"], self.path_executable,
                        match["player_two_id"], match["player_two_name"], opponent_path_executable)
                test_futures.append([test, future])
                result_id += 1

                # Play also reversed game (first player as second) so it is fair
                future = common.executor.submit(runner.play, result_id, test, self.tester,
                        match["player_two_id"], match["player_two_name"], opponent_path_executable,
                        match["player_one_id"], match["player_one_name"], self.path_executable)
                test_futures.append([test, future])
                result_id += 1

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

        self.logger.info("[Submission {}]     -- executed {} matches in {:.3f}s.".format(
            self.id, len(self.matches), perf_counter() - start_time))
        return errors

    def run_solution(self):
        if self.tester is None:
            run_status = self.process_tests()
            if run_status != "":
                self.logger.info("[Submission {}] Error while processing the tests: {}!".format(self.id, run_status))
                return False
        # If a game, set-up the runner and opponents' solutions, then simulate the game
        else:
            run_status = self.process_games()
            if run_status != "":
                self.logger.info("[Submission {}] Error while processing the games: {}!".format(self.id, run_status))
                return False
        return True

    def cleanup(self):
        self.logger.info("[Submission {}] Cleaning up sandbox...".format(self.id))
        if path.exists(self.path_sandbox):
            shutil.rmtree(self.path_sandbox)
