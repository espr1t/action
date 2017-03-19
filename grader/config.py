#
# Grader configuration
#

WORKER_COUNT = 3  # Threads

# Evaluation
UPDATE_INTERVAL = 0.5  # Seconds
FILE_DOWNLOAD_CHUNK_SIZE = 1048576  # 1 MB of data

# Execution
EXECUTION_MIN_CHECK_INTERVAL = 0.0001  # Seconds
EXECUTION_MAX_CHECK_INTERVAL = 0.01  # Seconds

# Start time taken by non-user actions
TIME_OFFSET_CPP = 0.0  # Seconds
TIME_OFFSET_JAVA = 0.1  # Seconds

# Memory used by non-user code
MEMORY_OFFSET_CPP = 1800000  # Bytes
MEMORY_OFFSET_JAVA = 26000000  # Bytes

# Maximum number of threads
THREAD_LIMIT_CPP = 2  # Threads
THREAD_LIMIT_JAVA = 99  # Threads

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
