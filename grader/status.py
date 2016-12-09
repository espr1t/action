"""
Provides constants for several possible grading events / statuses
"""

from enum import Enum


class TestStatus(Enum):
    """ Defines variable results (or progress) of running the tests. """
    PREPARING = 0
    COMPILING = 1
    QUEUED = 2
    TESTING = 3
    COMPILATION_ERROR = 4
    WRONG_ANSWER = 5
    RUNTIME_ERROR = 6
    TIME_LIMIT = 7
    MEMORY_LIMIT = 8
    INTERNAL_ERROR = 9
    ACCEPTED = 10

