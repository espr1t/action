import os
import config
import common

logger = common.get_logger(__name__)


class RunInfo:
    def __init__(self, time_limit, memory_limit, solution_path, tester_path=None, checker_path=None, compare_floats=False):
        self.time_limit = time_limit
        self.memory_limit = memory_limit
        self.solution_path = solution_path
        self.tester_path = tester_path
        self.checker_path = checker_path
        self.compare_floats = compare_floats

        self.solution_name = os.path.basename(self.solution_path)
        self.solution_language = common.get_language_by_exec_name(self.solution_name)

        if self.tester_path is not None:
            self.tester_name = os.path.basename(self.tester_path)
            self.tester_language = common.get_language_by_exec_name(self.tester_path)

        if self.checker_path is not None:
            self.checker_name = os.path.basename(self.checker_path)
            self.checker_language = common.get_language_by_exec_name(self.checker_path)

        # Determine actual time and memory limits
        # (this accounts for JVM startup time and memory overhead)
        self.time_offset = RunInfo.get_time_offset(self.solution_language)
        self.memory_offset = RunInfo.get_memory_offset(self.solution_language)

        # Terminate after TL + 0.2 or TL + 10% (whichever larger)
        self.timeout = self.time_limit + self.time_offset + max(0.2, self.time_limit * 0.1)

    @staticmethod
    def get_time_offset(language):
        if language == config.LANGUAGE_CPP:
            return config.TIME_OFFSET_CPP
        if language == config.LANGUAGE_JAVA:
            return config.TIME_OFFSET_JAVA
        if language == config.LANGUAGE_PYTHON:
            return config.TIME_OFFSET_PYTHON
        raise Exception("Unsupported language")

    @staticmethod
    def get_memory_offset(language):
        if language == config.LANGUAGE_CPP:
            return config.MEMORY_OFFSET_CPP
        if language == config.LANGUAGE_JAVA:
            return config.MEMORY_OFFSET_JAVA
        if language == config.LANGUAGE_PYTHON:
            return config.MEMORY_OFFSET_PYTHON
        raise Exception("Unsupported language")
