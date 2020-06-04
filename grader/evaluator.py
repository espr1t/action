"""
Handles grading of a submission.
The grading consists the following steps:
    1. The code is being compiled. An error is returned if there is a compilation error.
    2. The specified tests are being downloaded (if not already present).
    3. The solution is being executed against each test case in a sandbox.
Updates the frontend after each test case, but not more often than 0.33 sec (with the last test being an exception).
"""

import os
import shutil
from time import perf_counter

import config
import common
from common import TestStatus, TestInfo
import network
from updater import Updater
from compiler import Compiler
from execute_game import execute_game
from execute_problem import execute_problem
from runner import RunConfig


logger = common.get_logger(__file__)


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
        self.tests = [
            TestInfo(
                inpFile=test["inpFile"],
                inpHash=test["inpHash"],
                solFile=test["solFile"],
                solHash=test["solHash"],
                position=test["position"]
            ) for test in data["tests"]
        ]
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
        self.path_sandbox = os.path.join(config.PATH_SANDBOX, "submits", "submit_{:06d}/".format(self.id))
        self.path_source = os.path.join(self.path_sandbox, config.SOURCE_NAME + common.get_source_extension(self.language))
        self.path_executable = os.path.join(self.path_sandbox, config.EXECUTABLE_NAME + common.get_executable_extension(self.language))

        # Frontend server update logic
        self.updater = Updater(self.update_url, self.id, self.tests)

    def __del__(self):
        self.cleanup()

    def evaluate(self):
        # Send an update that preparation has been started for executing this submission
        logger.info("Submit {id} | Evaluating submit {submit_id}".format(id=self.id, submit_id=self.id))
        self.updater.add_info(status=TestStatus.PREPARING)

        # Create sandbox directory
        logger.info("Submit {id} |   >> creating sandbox directory...".format(id=self.id))
        if not self.create_sandbox_dir():
            self.updater.add_info(message="Error while creating sandbox directory!", status=TestStatus.INTERNAL_ERROR)
            return

        # Download the test files (if not downloaded already)
        logger.info("Submit {id} |   >> downloading test files...".format(id=self.id))
        if not self.download_tests():
            self.updater.add_info(message="Error while downloading test files!", status=TestStatus.INTERNAL_ERROR)
            return

        # Download and compile the checker (if not already available)
        if self.path_checker_executable is not None:
            if not os.path.exists(self.path_checker_executable):
                logger.info("Submit {id} |   >> downloading and compiling checker...".format(id=self.id))
                if not self.setup_utility_file(self.path_checker_source, self.path_checker_executable, self.checker_url):
                    self.updater.add_info(message="Error while setting up checker!", status=TestStatus.INTERNAL_ERROR)
                    return

        # Download and compile the tester (if not already available)
        if self.path_tester_executable is not None:
            if not os.path.exists(self.path_tester_executable):
                logger.info("Submit {id} |   >> downloading and compiling tester...".format(id=self.id))
                if not self.setup_utility_file(self.path_tester_source, self.path_tester_executable, self.tester_url):
                    self.updater.add_info(message="Error while setting up tester!", status=TestStatus.INTERNAL_ERROR)
                    return

        # Send an update that the compilation has been started for this submission
        self.updater.add_info(status=TestStatus.COMPILING)

        # Save the source to a file so we can compile it later
        logger.info("Submit {id} |   >> writing source code to file...".format(id=self.id))
        if not self.write_source(self.source, self.path_source):
            self.updater.add_info(message="Error while writing the source to a file!", status=TestStatus.INTERNAL_ERROR)
            return

        # Compile
        logger.info("Submit {id} |   >> compiling solution...".format(id=self.id))
        compilation_status = self.compile(self.language, self.path_source, self.path_executable)
        if compilation_status != "":
            self.updater.add_info(message=compilation_status, status=TestStatus.COMPILATION_ERROR)
            return

        # Run the solution on the problem's tests or with the provided tester
        logger.info("Submit {id} |   >> starting evaluation of solution...".format(id=self.id))
        if not self.run_solution():
            self.updater.add_info(message="Error while processing the solution!", status=TestStatus.INTERNAL_ERROR)
            return

        # Finished with this submission
        logger.info("Submit {id} | Completed evaluating submit {submit_id}.".format(id=self.id, submit_id=self.id))
        self.updater.add_info(message="DONE")

    def create_sandbox_dir(self):
        try:
            # Delete if already present (maybe regrade?)
            if os.path.exists(self.path_sandbox):
                shutil.rmtree(self.path_sandbox)
            # Create the submit testing directory
            if not os.path.exists(self.path_sandbox):
                os.makedirs(self.path_sandbox)
        except OSError as ex:
            logger.error("Submit {id} | Could not create sandbox directory. Exception was: {ex}".format(
                    id=self.id, ex=str(ex)))
            return False
        return True

    def download_test(self, file_name, file_hash, file_path):
        # Download only if the file doesn't already exist
        if not os.path.exists(file_path):
            logger.info("Submit {id} | Downloading file {file_name} with hash {file_hash} from URL: {url}".format(
                    id=self.id, file_name=file_name, file_hash=file_hash, url=self.tests_url + file_name))
            network.download_file(self.tests_url + file_name, file_path)

    def download_tests(self):
        # In case the directory for the tests does not exist, create it
        if not os.path.exists(config.PATH_TESTS):
            os.makedirs(config.PATH_TESTS)

        try:
            for test in self.tests:
                self.download_test(test.inpFile, test.inpHash, test.inpPath)
                self.download_test(test.solFile, test.solHash, test.solPath)
        except Exception as ex:
            logger.error("Submit {id} | Could not download tests. Exception was: {ex}".format(id=self.id, ex=str(ex)))
            return False
        return True

    def compile(self, language, path_source, path_executable):
        try:
            return Compiler.compile(language, path_source, path_executable)
        except Exception as ex:
            # If a non-compiler error occurred, log the message in addition to sending it to the user
            logger.error("Submit {id} | Could not compile file {file_path}! Exception was: {ex}".format(
                    id=self.id, file_path=path_source, ex=str(ex)))
            return "Internal Error: " + str(ex)

    def compile_utility_file(self, path_source, path_executable):
        # If already compiled, return directly
        if os.path.exists(path_executable):
            return True
        # Otherwise do the compilation
        logger.info("Submit {id} |   >> compiling utility file {file_name}...".format(
                id=self.id, file_name=os.path.basename(path_source)))
        return self.compile(config.LANGUAGE_CPP, path_source, path_executable) == ""

    def download_utility_file(self, download_url, destination):
        # If already downloaded, return directly
        if os.path.exists(destination):
            return True
        # Otherwise download it
        logger.info("Submit {id} |   >> downloading utility file {file_name}".format(
                id=self.id, file_name=download_url.split('/')[-1]))
        try:
            network.download_file(download_url, destination)
        except RuntimeError:
            return False

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
            logger.error("Submit {id} | Could not write source file. Exception was: {ex}".format(
                    id=self.id, ex=str(ex)))
            return False
        return True

    def process_problem(self):
        start_time = perf_counter()
        completed_successfully = True

        run_config = RunConfig(
            time_limit=self.time_limit,
            memory_limit=self.memory_limit,
            executable_path=self.path_executable,
            checker_path=self.path_checker_executable,
            tester_path=self.path_tester_executable,
            compare_floats=self.floats
        )

        test_futures = []
        result_id = 0
        for test in self.tests:
            future = common.job_pool.submit(execute_problem, self.updater, self.id, result_id, test, run_config)
            test_futures.append((test, future))
            result_id += 1

        for test, future in test_futures:
            try:
                future.result()  # Wait for the test to be executed
            except Exception as ex:
                completed_successfully = False
                logger.error("Submit {id} | Exception on test {test_name} ({test_hash}): {ex}".format(
                        id=self.id, test_name=test.inpFile, test_hash=test.inpHash, ex=str(ex)))

        logger.info("Submit {id} |   >> executed {cnt} tests in {time:.3f}s.".format(
            id=self.id, cnt=len(self.tests), time=perf_counter() - start_time))
        return completed_successfully

    def process_game(self):
        start_time = perf_counter()
        completed_successfully = True

        run_config = RunConfig(
            time_limit=self.time_limit,
            memory_limit=self.memory_limit,
            executable_path=self.path_executable,
            tester_path=self.path_tester_executable
        )

        result_id = 0
        for match in self.matches:
            logger.info("Submit {id} |     -- running game {player1} vs {player2}...".format(
                id=self.id, player1=match["player_one_name"], player2=match["player_two_name"]))

            # Get and compile the opponent's solution
            opponent_language = match["language"]
            opponent_path_source = os.path.join(self.path_sandbox,
                    config.OPPONENT_SOURCE_NAME + common.get_source_extension(opponent_language))
            opponent_path_executable = os.path.join(self.path_sandbox,
                    config.OPPONENT_EXECUTABLE_NAME + common.get_executable_extension(opponent_language))

            logger.info("Submit {id} |       ++ writing opponent's source...".format(id=self.id))
            if not self.write_source(match["source"], opponent_path_source):
                logger.error("Submit {id} | Could not write opponent's source!".format(id=self.id))
                continue
            logger.info("Submit {id} |       ++ compiling opponent's source...".format(id=self.id))
            if self.compile(opponent_language, opponent_path_source, opponent_path_executable) != "":
                logger.error("Submit {id} | Could not compile opponent's source!".format(id=self.id))
                continue

            # Run all of the game's tests for this pair of solutions
            test_futures = []
            for test in self.tests:
                # Play forward game
                future = common.job_pool.submit(execute_game, self.updater, self.id, result_id, test, run_config,
                        match["player_one_id"], match["player_one_name"], self.path_executable,
                        match["player_two_id"], match["player_two_name"], opponent_path_executable)
                test_futures.append([test, future])
                result_id += 1

                # Play also reversed game (first player as second) so it is fair
                future = common.job_pool.submit(execute_game, self.updater, self.id, result_id, test, run_config,
                        match["player_two_id"], match["player_two_name"], opponent_path_executable,
                        match["player_one_id"], match["player_one_name"], self.path_executable)
                test_futures.append([test, future])
                result_id += 1

            for test, future in test_futures:
                try:
                    future.result()  # Wait for the test to be executed
                except Exception as ex:
                    completed_successfully = False
                    logger.error("Submit {id} | Exception on test {test_name} ({test_hash}): {ex}".format(
                        id=self.id, test_name=test.inpFile, test_hash=test.inpHash, ex=str(ex)))

        logger.info("Submit {id} |     -- executed {cnt} matches in {time:.3f}s.".format(
            id=self.id, cnt=len(self.matches), time=perf_counter() - start_time))
        return completed_successfully

    def run_solution(self):
        if self.problem_type == "interactive" or self.problem_type == "game":
            if self.path_tester_executable is None:
                logger.error("Submit {id} | Game or interactive problem without a tester!".format(id=self.id))
                return False

        if self.path_tester_executable is not None and self.problem_type != "interactive":
            return self.process_game()
        else:
            return self.process_problem()

    def cleanup(self):
        # Clean up remaining files
        if os.path.exists(self.path_sandbox):
            shutil.rmtree(self.path_sandbox)
