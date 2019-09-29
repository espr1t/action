"""
Provides constants for several possible grading events / statuses
"""

from enum import Enum

import common

logger = common.get_logger(__name__)


class TestStatus(Enum):
    """ Defines variable results (or progress) of running the tests. """
    PREPARING = 0
    COMPILING = 1
    TESTING = 2
    COMPILATION_ERROR = 3
    WRONG_ANSWER = 4
    RUNTIME_ERROR = 5
    TIME_LIMIT = 6
    MEMORY_LIMIT = 7
    INTERNAL_ERROR = 8
    ACCEPTED = 9


