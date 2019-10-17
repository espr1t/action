#
# Handles grading of a submission.
# The grading consists the following steps:
#     1. The code is being compiled. An error is returned if there is a compilation error.
#     2. The specified tests are being downloaded (if not already present).
#     3. The solution is being executed against each test case in a sandbox.
# Updates the frontend after each test case, but no more often than 0.5 sec (with the last test being an exception).
#

import shutil
import os
from time import perf_counter

import config
import common
from updater import Updater
from compiler import Compiler
from status import TestStatus
from runner import Runner

logger = common.get_logger(__name__)


class Evaluator:
    def __init__(self, data):
        # Submit information
        self.id = data["id"]
        self.source = data["source"]
        self.language = data["language"]
        self.time_limit = data["timeLimit"]
        self.memory_limit = data["memoryLimit"] * 1048576  # Given in MiB, convert to bytes
        self.problem_type = data["problemType"]

        # Front-end endpoint
        self.update_url = data["updateEndpoint"]

        # List of tests and endpoint where to download them from
        self.tests = data["tests"]
        self.tests_url = data["testsEndpoint"]

        # If a task with checker, there should also be an endpoint where to download it from
        self.path_checker_source = None
        self.path_checker_executable = None
        if "checker" in data and data["checker"] != "":
            self.path_checker_source = os.path.join(config.PATH_CHECKERS, data["checker"] + config.SOURCE_EXTENSION_CPP)
            self.path_checker_executable = os.path.join(config.PATH_CHECKERS, data["checker"] + config.EXECUTABLE_EXTENSION_CPP)
        self.checker_url = data["checkerEndpoint"] if "checkerEndpoint" in data else None

        # If a task with tester, there should also be an endpoint where to download it from
        self.path_tester_source = None
        self.path_tester_executable = None
        if "tester" in data and data["tester"] != "":
            self.path_tester_source = os.path.join(config.PATH_TESTERS, data["tester"] + config.SOURCE_EXTENSION_CPP)
            self.path_tester_executable = os.path.join(config.PATH_TESTERS, data["tester"] + config.EXECUTABLE_EXTENSION_CPP)
        self.tester_url = data["testerEndpoint"] if "testerEndpoint" in data else None

        # If a game, there should also be a list of matches (opponents' names and solutions)
        self.matches = data["matches"] if "matches" in data else None

        # Whether to use relative or absolute floating point comparison
        self.floats = data["floats"]

        # Path to sandbox and files inside
        self.path_sandbox = os.path.join(config.PATH_SANDBOX, "submit_{:06d}/".format(self.id))
        self.path_source = os.path.join(self.path_sandbox, config.SOURCE_NAME + common.get_source_extension(self.language))
        self.path_executable = os.path.join(self.path_sandbox, config.EXECUTABLE_NAME + common.get_executable_extension(self.language))

        # Frontend server update logic
        self.updater = Updater(self.update_url, self.id, self.tests)

    def __del__(self):
        self.cleanup()

    def evaluate(self):
        # Send an update that preparation has been started for executing this submission
        logger.info("Submit {} | Evaluating submit {}".format(self.id, self.id))
        self.updater.add_info("", None, TestStatus.PREPARING)

        # Create sandbox directory
        logger.info("Submit {} |   >> creating sandbox directory...".format(self.id))
        if not self.create_sandbox_dir():
            self.updater.add_info("Error while creating sandbox directory!", None, TestStatus.INTERNAL_ERROR)
            return

        # Download the test files (if not downloaded already)
        logger.info("Submit {} |   >> downloading test files...".format(self.id))
        if not self.download_tests():
            self.updater.add_info("Error while downloading test files!", None, TestStatus.INTERNAL_ERROR)
            return

        # Download and compile the checker (if not already available)
        if self.path_checker_executable is not None:
            if not os.path.exists(self.path_checker_executable):
                logger.info("Submit {} |   >> downloading and compiling checker...".format(self.id))
                if not self.setup_utility_file(self.path_checker_source, self.path_checker_executable, self.checker_url):
                    self.updater.add_info("Error while setting up checker!", None, TestStatus.INTERNAL_ERROR)
                    return

        # Download and compile the tester (if not already available)
        if self.path_checker_executable is not None:
            if not os.path.exists(self.path_checker_executable):
                logger.info("Submit {} |   >> downloading and compiling tester...".format(self.id))
                if not self.setup_utility_file(self.path_tester_source, self.path_tester_executable, self.tester_url):
                    self.updater.add_info("Error while setting up tester!", None, TestStatus.INTERNAL_ERROR)
                    return

        # Send an update that the compilation has been started for this submission
        self.updater.add_info("", None, TestStatus.COMPILING)

        # Save the source to a file so we can compile it later
        logger.info("Submit {} |   >> writing source code to file...".format(self.id))
        if not self.write_source(self.source, self.path_source):
            self.updater.add_info("Error while writing the source to a file!", None, TestStatus.INTERNAL_ERROR)
            return

        # Compile
        logger.info("Submit {} |   >> compiling solution...".format(self.id))
        compilation_status = self.compile(self.language, self.path_source, self.path_executable)
        if compilation_status != "":
            logger.info("Submit {} | Compilation error! Aborting...".format(self.id))
            self.updater.add_info(compilation_status, None, TestStatus.COMPILATION_ERROR)
            return

        # If a standard task, just run the solution on the given tests
        logger.info("Submit {} |   >> starting evaluation of solution...".format(self.id))
        if not self.run_solution():
            logger.info("Submit {} | Error while processing the solution! Aborting...".format(self.id))
            self.updater.add_info("Error while processing the solution!", None, TestStatus.INTERNAL_ERROR)
            return

        # Finished with this submission
        logger.info("Submit {} | Completed evaluating submit {}.".format(self.id, self.id))
        self.updater.add_info("DONE", None, None)

    def create_sandbox_dir(self):
        try:
            # Delete if already present (maybe regrade?)
            if os.path.exists(self.path_sandbox):
                shutil.rmtree(self.path_sandbox)
            # Create the submit testing directory
            if not os.path.exists(self.path_sandbox):
                os.makedirs(self.path_sandbox)
        except OSError as ex:
            logger.error("Submit {} | Could not create sandbox directory. Error was: {}".format(self.id, str(ex)))
            return False
        return True

    def download_test(self, test_name, test_hash):
        test_path = os.path.join(config.PATH_TESTS, test_hash)
        # Download only if the file doesn't already exist
        if not os.path.exists(test_path):
            logger.info("Submit {} | Downloading file {} with hash {} from URL: {}".format(
                self.id, test_name, test_hash, self.tests_url + test_name))
            common.download_file(self.tests_url + test_name, test_path)

    def download_tests(self):
        # In case the directory for the tests does not exist, create it
        if not os.path.exists(config.PATH_TESTS):
            os.makedirs(config.PATH_TESTS)

        try:
            for test in self.tests:
                self.download_test(test["inpFile"], test["inpHash"])
                self.download_test(test["solFile"], test["solHash"])
        except Exception as ex:
            logger.error("Submit {} | Could not download tests. Error was: {}".format(self.id, str(ex)))
            return False
        return True

    def compile(self, language, path_source, path_executable):
        try:
            return common.executor.submit(Compiler.compile, language, path_source, path_executable).result()
        except ValueError as ex:
            # If a non-compiler error occurred, log the message in addition to sending it to the user
            logger.error("Submit {} | Could not compile file {}! Error was: {}".format(
                self.id, path_source, str(ex)))
            return "Internal Error: " + str(ex)

    def compile_utility_file(self, path_source, path_executable):
        # Only compile if not already compiled
        if not os.path.exists(path_executable):
            logger.info("Submit {} |   >> compiling utility file {}...".format(
                self.id, os.path.basename(path_source)))
            return self.compile(config.LANGUAGE_CPP, path_source, path_executable) == ""
        return True

    def download_utility_file(self, download_url, destination):
        # Only download if not downloaded already
        if not os.path.exists(destination):
            logger.info("Submit {} |   >> downloading utility file {}".format(self.id, download_url.split('/')[-1]))
            try:
                common.download_file(download_url, destination)
            except RuntimeError:
                return False
        return True

    def setup_utility_file(self, path_source, path_executable, download_url):
        if not self.download_utility_file(download_url, path_source):
            return False
        if not self.compile_utility_file(path_source, path_executable):
            return False
        return True

    def write_source(self, source, destination):
        try:
            with open(destination, "w") as file:
                file.write(source)
        except OSError as ex:
            logger.error("Submit {} | Could not write source file. Error: ".format(self.id, str(ex)))
            return False
        return True

    def process_tests(self):
        start_time = perf_counter()
        runner = Runner(self)
        errors = ""

        test_futures = []
        for result_id in range(len(self.tests)):
            if self.problem_type != "interactive":
                future = common.executor.submit(runner.run_problem, result_id, self.tests[result_id])
            else:
                future = common.executor.submit(runner.run_interactive_problem, result_id, self.tests[result_id])
            test_futures.append((self.tests[result_id], future))

        for test, future in test_futures:
            try:
                # Wait for the test to be executed
                future.result()
            except Exception as ex:
                errors += "Internal error on test " + test["inpFile"] + "(" + test["inpHash"] + "): " + str(ex)
                logger.error("Submit {} | Exception: {}".format(self.id, str(ex)))

        logger.info("Submit {} |   >> executed {} tests in {:.3f}s.".format(
            self.id, len(self.tests), perf_counter() - start_time))
        return errors

    def process_games(self):
        start_time = perf_counter()
        runner = Runner(self)
        errors = ""

        result_id = 0
        for match in self.matches:
            logger.info("Submit {} |     -- running game {} vs {}...".format(
                self.id, match["player_one_name"], match["player_two_name"]))

            # Get and compile the opponent's solution
            opponent_language = match["language"]
            opponent_path_source = os.path.join(self.path_sandbox, config.OPPONENT_SOURCE_NAME +
                common.get_source_extension(opponent_language))
            opponent_path_executable = os.path.join(self.path_sandbox, config.OPPONENT_EXECUTABLE_NAME +
                common.get_executable_extension(opponent_language))

            logger.info("Submit {} |       ++ writing opponent's source...".format(self.id))
            if not self.write_source(match["source"], opponent_path_source):
                logger.error("Submit {} | Could not write opponent's source!".format(self.id))
                continue
            logger.info("Submit {} |       ++ compiling opponent's source...".format(self.id))
            if self.compile(opponent_language, opponent_path_source, opponent_path_executable) != "":
                logger.error("Submit {} | Could not compile opponent's source!".format(self.id))
                continue

            # Run all of the game's tests for this pair of solutions
            test_futures = []
            for test in self.tests:
                # Play forward game
                future = common.executor.submit(runner.run_game, result_id, test,
                                                match["player_one_id"], match["player_one_name"], self.path_executable,
                                                match["player_two_id"], match["player_two_name"], opponent_path_executable)
                test_futures.append([test, future])
                result_id += 1

                # Play also reversed game (first player as second) so it is fair
                future = common.executor.submit(runner.run_game, result_id, test,
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
                    logger.error("Submit {} | Exception: {}".format(self.id, str(ex)))
                    break
                except Exception as ex:
                    logger.error("Submit {} | Exception: {}".format(self.id, str(ex)))

        logger.info("Submit {} |     -- executed {} matches in {:.3f}s.".format(
            self.id, len(self.matches), perf_counter() - start_time))
        return errors

    def run_solution(self):
        if self.path_tester_executable is not None and self.problem_type != 'interactive':
            run_status = self.process_games()
            if run_status != "":
                logger.info("Submit {} | Error while processing the games: {}".format(self.id, run_status))
                return False
        else:
            run_status = self.process_tests()
            if run_status != "":
                logger.info("Submit {} | Error while processing the tests: {}".format(self.id, run_status))
                return False
        # If a game, set-up the runner and opponents' solutions, then simulate the game
        return True

    def cleanup(self):
        # Clean up remaining files
        if os.path.exists(self.path_sandbox):
            shutil.rmtree(self.path_sandbox)
