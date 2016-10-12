"""
Compiles or parses source files and returns information if an error arises.
"""

import logging
import subprocess
from os import getcwd


class Compiler:
    COMPILE_LINE_CPP = "g++ -O2 -std=c++11 -o {path_executable} {path_source}"
    COMPILE_LINE_JAVA = "javac {source_file}"
    COMPILE_LINE_PYTHON = "python -m py_compile {source_file}"

    @staticmethod
    def compile(path_source, language, path_executable):
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
    def compile_cpp(path_source, path_executable):
        # TODO: Implement compilation timeout

        logging.info("Compiling source {}".format(path_source))
        command = Compiler.COMPILE_LINE_CPP.format(path_source=path_source, path_executable=path_executable)
        process = subprocess.Popen(command, stderr=subprocess.PIPE, shell=True, cwd=getcwd())
        error = process.communicate()[1]
        return "" if error is None else error.decode("utf-8")

    @staticmethod
    def compile_java(path_source, path_executable):
        # TODO: Implement
        return "Unsupported Language (Java)!"

    @staticmethod
    def compile_python(path_source, path_executable):
        # TODO: Implement
        return "Unsupported Language (Python)!"

