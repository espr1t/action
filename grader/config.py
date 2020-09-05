"""
Grader configuration
Specifies constants used for running the grader
"""

from os import path

# Usernames and passwords should be Latin letters, digits and most ASCII symbols.
# Do not use arbitrary UTF-8 characters as the utf8_encode() algorithm in PHP
# and the one in Python are apparently different and produce different results.
AUTH_USERNAME = "username"
AUTH_PASSWORD = "password"

# NOTE: The number of simultaneously processed jobs is greater than the executor
# count as there is some non CPU-intensive work that can be done in parallel to
# the (CPU-intensive) running solutions - like preparing the runs, downloading
# tests, writing sources, sending updates, etc.

# The best count for MAX_PARALLEL_EXECUTORS is around 75% of the CPU cores
# available (and at least one less than the number of cores) as this prevents
# the system from freezing while executing solutions. This way it is able to
# process requests and do other miscellaneous work in the meantime.

# Parallelism
NUM_PHYSICAL_CPUS = 1
MAX_PARALLEL_EXECUTORS = 3
MAX_PARALLEL_SUBMITS = MAX_PARALLEL_EXECUTORS
MAX_PARALLEL_JOBS = MAX_PARALLEL_EXECUTORS * 2

# Communication with the front-end
UPDATE_INTERVAL = 0.33  # Seconds
FILE_DOWNLOAD_CHUNK_SIZE = 1048576  # 1 MB of data

# Compilation
MAX_COMPILATION_TIME = 10.0  # Seconds

# Checker and tester
CHECKER_TIMEOUT = 5.0  # Seconds
MAX_TESTER_MEMORY = 268435456  # Bytes = 256MB
MAX_CHECKER_MEMORY = 268435456  # Bytes = 256MB

# Priority
PROCESS_PRIORITY_NICE = -20  # In [-20, 19], -20 is highest
PROCESS_PRIORITY_REAL = +50  # In [  1, 99], +99 is highest

# Execution limits
MAX_OPEN_FILES = 32
MAX_PROCESSES = 64
MAX_EXECUTION_TIME = 300  # Seconds = 5 minutes
MAX_EXECUTION_MEMORY = 2147483648  # Bytes = 2GB
MAX_EXECUTION_STACK = 67108864  # Bytes = 64MB
MAX_EXECUTION_OUTPUT = 16777216  # Bytes = 16MB (actually set to the nearest larger power of two)

# Games
MAX_GAME_LENGTH = MAX_EXECUTION_TIME - 1

# Start time taken by non-user actions
TIME_OFFSET_CPP = 0.0  # Seconds
TIME_OFFSET_JAVA = 0.05  # Seconds
TIME_OFFSET_PYTHON = 0.0  # Seconds

# Memory used by non-user code
MEMORY_OFFSET_CPP = 2 * (1 << 20)  # Bytes = 2MB
MEMORY_OFFSET_JAVA = 34 * (1 << 20)  # Bytes = 34MB
MEMORY_OFFSET_PYTHON = 9 * (1 << 20)  # Bytes = 9MB

# Output Validation
FLOAT_PRECISION = 1e-9

# Output Encoding
OUTPUT_ENCODING = "cp855"

# File names
SOURCE_NAME = "source"
EXECUTABLE_NAME = "executable"
OPPONENT_SOURCE_NAME = "opponent_source"
OPPONENT_EXECUTABLE_NAME = "opponent_executable"

# Languages
LANGUAGE_CPP = "C++"
LANGUAGE_JAVA = "Java"
LANGUAGE_PYTHON = "Python"

# File extensions
SOURCE_EXTENSION_CPP = ".cpp"
SOURCE_EXTENSION_JAVA = ".java"
SOURCE_EXTENSION_PYTHON = ".py"

EXECUTABLE_EXTENSION_CPP = ".o"
EXECUTABLE_EXTENSION_JAVA = ".jar"
EXECUTABLE_EXTENSION_PYTHON = ".py"

# Paths
ROOT_DIR = path.dirname(path.abspath(__file__))
PATH_TESTS = path.abspath(path.join(ROOT_DIR, "data/tests/"))
PATH_CHECKERS = path.abspath(path.join(ROOT_DIR, "data/checkers/"))
PATH_TESTERS = path.abspath(path.join(ROOT_DIR, "data/testers/"))
PATH_REPLAYS = path.abspath(path.join(ROOT_DIR, "data/replays/"))
PATH_SANDBOX = path.abspath(path.join(ROOT_DIR, "sandbox/"))
PATH_LOG_FILE = path.abspath(path.join(ROOT_DIR, "logs/grader.log"))
