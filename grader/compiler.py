"""
Compiles or parses source files and returns information if an error arises.
"""

import logging
import psutil
from subprocess import PIPE
from time import sleep, perf_counter

import config


class Compiler:
    COMPILE_LINE_CPP = "g++ -O2 -std=c++14 -w -o {path_executable} {path_source}"
    COMPILE_LINE_JAVA = "javac {source_file}"
    COMPILE_LINE_PYTHON = "python -m py_compile {source_file}"

    @staticmethod
    def compile(language, path_source, path_executable):
        """ Compiles (or parses) a source file and returns the name of the produced binary
        as well as a string, containing the error message if the operation was unsuccessful.
        """
        if language == "C++":
            return Compiler.compile_cpp(path_source, path_executable)
        elif language == "Java":
            return Compiler.compile_java(path_source, path_executable)
        elif language == "Python":
            return Compiler.compile_python(path_source, path_executable)
        else:
            raise ValueError("Unknown Language {}!".format(language))

    @staticmethod
    def run_compiler(command):
        start_time = perf_counter()
        process = psutil.Popen(args=command, stderr=PIPE, shell=True)
        while True:
            sleep(config.EXECUTION_CHECK_INTERVAL)

            # Process already terminated
            exit_code = process.poll()
            if exit_code is not None or not process.is_running:
                break

            # Compilation is taking too much time
            if perf_counter() - start_time > config.MAX_COMPILATION_TIME:
                # Kill the shell and the compilation process
                try:
                    for child in process.children(recursive=True):
                        child.kill()
                    process.kill()
                except psutil.NoSuchProcess:
                    pass
                break

        error_message = process.communicate()[1]
        error_message = error_message.decode("utf-8") if error_message is not None else ""
        return exit_code, error_message, perf_counter() - start_time

    @staticmethod
    def compile_cpp(path_source, path_executable):
        logging.info("Compiling source {}".format(path_source))

        command = Compiler.COMPILE_LINE_CPP.format(path_executable=path_executable, path_source=path_source)
        exit_code, error_message, compilation_time = Compiler.run_compiler(command)

        if error_message != "":
            return "Compilation error: " + error_message

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        if exit_code != 0:
            return "Compilation exited with a non-zero exit code: {}".format(exit_code)

        return ""

    @staticmethod
    def compile_java(path_source, path_executable):
        # TODO: Implement
        return "Unsupported Language (Java)!"

    @staticmethod
    def compile_python(path_source, path_executable):
        # TODO: Implement
        return "Unsupported Language (Python)!"

