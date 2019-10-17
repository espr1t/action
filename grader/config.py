#
# Grader configuration
#

import os

# Change current working directory to the one the script is in
os.chdir(os.path.dirname(os.path.abspath(__file__)))


WORKER_COUNT = 3  # Max number of running solutions/containers

# Evaluation
UPDATE_INTERVAL = 0.33  # Seconds
FILE_DOWNLOAD_CHUNK_SIZE = 1048576  # 1 MB of data

# Execution
MAX_GAME_LENGTH = 300.0  # Seconds = 5 minutes

# Start time taken by non-user actions
TIME_OFFSET_CPP = 0.0  # Seconds
TIME_OFFSET_JAVA = 0.05  # Seconds
TIME_OFFSET_PYTHON = 0.0  # Seconds

# Memory used by non-user code
MEMORY_OFFSET_CPP = 1300000  # 1.3MB
MEMORY_OFFSET_JAVA = 36000000  # 36MB
MEMORY_OFFSET_PYTHON = 8000000  # 8MB

# Compilation
MAX_EXECUTION_TIME = 30.0  # Seconds
MAX_COMPILATION_TIME = 10.0  # Seconds

# Checker
CHECKER_TIMEOUT = 3.0  # Seconds

# Will not work with random UTF-8 characters since the utf8_encode() algorithm
# in PHP and Python is apparently different. Will work with Latin letters, digits and most symbols.
AUTH_USERNAME = "username"
AUTH_PASSWORD = "password"

PATH_DATA = os.path.abspath(os.path.join(os.getcwd(), "data/"))
PATH_TESTS = os.path.abspath(os.path.join(os.getcwd(), "data/tests/"))
PATH_CHECKERS = os.path.abspath(os.path.join(os.getcwd(), "data/checkers/"))
PATH_TESTERS = os.path.abspath(os.path.join(os.getcwd(), "data/testers/"))
PATH_REPLAYS = os.path.abspath(os.path.join(os.getcwd(), "data/replays/"))
PATH_SANDBOX = os.path.abspath(os.path.join(os.getcwd(), "sandbox/"))
PATH_LOG_FILE = os.path.abspath(os.path.join(os.getcwd(), "logs/grader.log"))

SOURCE_NAME = "source"
EXECUTABLE_NAME = "executable"
OPPONENT_SOURCE_NAME = "opponent_source"
OPPONENT_EXECUTABLE_NAME = "opponent_executable"

SOURCE_EXTENSION_CPP = ".cpp"
SOURCE_EXTENSION_JAVA = ".java"
SOURCE_EXTENSION_PYTHON = ".py"

EXECUTABLE_EXTENSION_CPP = ".o"
EXECUTABLE_EXTENSION_JAVA = ".jar"
EXECUTABLE_EXTENSION_PYTHON = ".py"

# Output Validation
FLOAT_PRECISION = 1e-9

# Languages
LANGUAGE_CPP = "C++"
LANGUAGE_JAVA = "Java"
LANGUAGE_PYTHON = "Python"
