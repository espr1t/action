"""
Compiles or parses source files and returns information if an error arises.
"""

import os
import psutil
from shutil import copyfile
from subprocess import PIPE
from time import sleep, perf_counter

import config


class Compiler:
    COMPILE_LINE_CPP = "g++ -O2 -std=c++14 -w -s -static -o {path_executable} {path_source}"
    COMPILE_LINE_JAVA = "javac -nowarn -d {path_executable} {path_source}"
    COMPILE_LINE_PYTHON = "python3 -m pyflakes {path_source}"
    COMPILE_LINE_HASKELL = "ghc -O2 -w -o {path_executable} {path_source}"

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
        elif language == "Haskell":
            return Compiler.compile_haskell(path_source, path_executable)
        else:
            raise ValueError("Unknown Language {}!".format(language))

    @staticmethod
    def run_command(command):
        start_time = perf_counter()
        process = psutil.Popen(args=command, stdout=PIPE, stderr=PIPE, shell=True)
        while True:
            sleep(config.EXECUTION_MAX_CHECK_INTERVAL)

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

        output = process.communicate()
        stdout_output = output[0].decode("utf-8") if output[0] is not None else ""
        stderr_output = output[1].decode("utf-8") if output[1] is not None else ""
        return exit_code, stdout_output, stderr_output, perf_counter() - start_time

    @staticmethod
    def compile_cpp(path_source, path_executable):
        command = Compiler.COMPILE_LINE_CPP.format(path_executable=path_executable, path_source=path_source)
        exit_code, stdout_output, stderr_output, compilation_time = Compiler.run_command(command)

        if stderr_output != "":
            return "Compilation error: " + stderr_output

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        if exit_code != 0:
            return "Compilation exited with a non-zero exit code: {}".format(exit_code)

        return ""

    @staticmethod
    def compile_java(path_source, path_executable):
        # Javac expects directory, not complete path
        name_executable = os.path.basename(path_executable)
        path_executable = os.path.dirname(path_executable)
        class_name = os.path.basename(path_source).replace(".java", "")

        command = Compiler.COMPILE_LINE_JAVA.format(path_executable=path_executable, path_source=path_source)
        exit_code, stdout_output, stderr_output, compilation_time = Compiler.run_command(command)

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        # The main class is not named properly.
        # Do a dirty fix copying the source to a temporary file with a proper name to handle that.
        if "is public, should be declared in a file named" in stderr_output:
            class_name = stderr_output.split(" is public")[0].split()[-1]
            path_source_new = os.path.join(os.path.dirname(path_source), class_name + ".java")
            copyfile(path_source, path_source_new)

            # Let's try this again...
            command = Compiler.COMPILE_LINE_JAVA.format(path_executable=path_executable, path_source=path_source_new)
            exit_code, stdout_output, stderr_output, compilation_time = Compiler.run_command(command)
            # Remove temporary file
            os.remove(path_source_new)

        if stderr_output != "":
            return "Compilation error: " + stderr_output

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        if exit_code != 0:
            return "Compilation exited with a non-zero exit code: {}".format(exit_code)

        # We need to be in the sandbox dir to create the class file
        working_dir = os.getcwd()
        os.chdir(path_executable)

        # Create a jar with the compiled class file
        class_file = class_name + ".class"

        # If there is no class file, there was some problem with the compilation (e.g. empty source)
        if os.path.exists(class_file):
            manifest_file = "manifest.mf"
            jar_file = name_executable

            with open(manifest_file, "wt") as manifest:
                manifest.write("Manifest-version: 1.0\n")
                manifest.write("Main-Class: {}\n".format(class_name))

            command = "jar cfm {} {} *.class".format(jar_file, manifest_file)
            exit_code, stdout_output, stderr_output, compilation_time = Compiler.run_command(command)

            # Remove left-over files
            os.remove(class_file)
            os.remove(manifest_file)
        else:
            stderr_output = "Empty or buggy file provided."

        # Revert to original working dir
        os.chdir(working_dir)

        return stderr_output

    @staticmethod
    def compile_python(path_source, path_executable):
        command = Compiler.COMPILE_LINE_PYTHON.format(path_source=path_source)
        exit_code, stdout_output, stderr_output, compilation_time = Compiler.run_command(command)

        # Pyflakes prints its output to stdout instead of stderr
        if stdout_output != "":
            return "Compilation error: " + stdout_output

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        # The file seems to be parsed correctly, so place it as an executable
        copyfile(path_source, path_executable)
        return ""

    @staticmethod
    def compile_haskell(path_source, path_executable):
        command = Compiler.COMPILE_LINE_HASKELL.format(path_executable=path_executable, path_source=path_source)
        exit_code, stdout_output, stderr_output, compilation_time = Compiler.run_command(command)

        if stderr_output != "":
            return "Compilation error: " + stderr_output

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        if exit_code != 0:
            return "Compilation exited with a non-zero exit code: {}".format(exit_code)

        return ""
