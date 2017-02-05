#
# Grader configuration
#

WORKER_COUNT = 3  # Threads

# Evaluation
UPDATE_INTERVAL = 0.5  # Seconds
FILE_DOWNLOAD_CHUNK_SIZE = 1048576  # 1 MB of data

# Execution
EXECUTION_CHECK_INTERVAL = 0.01  # Seconds

# Compilation
MAX_COMPILATION_TIME = 3.0  # Seconds

# Checker
CHECKER_TIMEOUT = 3.0  # Seconds

# Will not work with random UTF-8 characters since the utf8_encode() algorithm
# in PHP and Python is apparently different. Will work with Latin letters, digits and most symbols.
AUTH_USERNAME = "username"
AUTH_PASSWORD = "password"

PATH_DATA = "data/"
PATH_TESTS = "data/tests/"
PATH_CHECKERS = "data/checkers/"
PATH_SANDBOX = "sandbox/"
PATH_LOG_FILE = "logs/grader.log"

SOURCE_NAME = "source"
EXECUTABLE_NAME = "executable"

# Output Validation
FLOAT_PRECISION = 1e-9
