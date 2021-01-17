"""
Compiles or parses source files and returns information if an error arises.
"""

import os
import random
import string

import config
from runner import Runner
from sandbox import Sandbox


class Compiler:
    COMPILE_COMMAND_CPP = "g++ -O2 -std=c++17 -Werror=return-type -s -o {executable} {source}"
    COMPILE_COMMAND_PYTHON = "pypy3 -m pyflakes {source}"
    COMPILE_COMMAND_JAVA = "javac -nowarn {source}"
    COMPILE_COMMAND_JAVA_JAR = "jar --create --file=result.jar --main-class={class_name} *.class"

    @staticmethod
    def compile(language, path_source, path_executable):
        # Check if the source contains only whitespace
        # This assumes that the source is saved as UTF-8 (which should be the case)
        with open(path_source, "rt") as source_file:
            source = source_file.read()
            if source == "" or source.isspace():
                return "Source is empty."

        # Compiles or parses a source file and returns a string containing the error message on failure.
        if language == config.LANGUAGE_CPP:
            return Compiler.compile_cpp(path_source, path_executable)
        elif language == config.LANGUAGE_JAVA:
            return Compiler.compile_java(path_source, path_executable)
        elif language == config.LANGUAGE_PYTHON:
            return Compiler.compile_python(path_source, path_executable)
        else:
            raise ValueError("Unknown Language {}!".format(language))

    @staticmethod
    def compile_cpp(path_source, path_executable):
        sandbox = Sandbox()
        sandbox.put_file(path_source)
        name_source = os.path.basename(path_source)
        name_executable = os.path.basename(path_executable)

        command = Compiler.COMPILE_COMMAND_CPP.format(executable=name_executable, source=name_source)
        run_result = Runner.run_command(
            sandbox=sandbox, command=command, timeout=config.MAX_COMPILATION_TIME, print_stderr=True, privileged=True
        )

        if run_result.exec_time > config.MAX_COMPILATION_TIME - 0.1:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)
        if run_result.exit_code != 0:
            return "Compilation error: {}".format(run_result.output.decode())

        sandbox.get_file(name_executable, path_executable)
        return ""

    @staticmethod
    def compile_java(path_source, path_executable):
        # Remove "package" directives (we don't need them here)
        with open(path_source, "rt") as inp:
            lines_left = [line for line in inp.readlines() if not line.strip().startswith("package")]
        with open(path_source, "wt") as out:
            out.writelines(lines_left)

        sandbox = Sandbox()

        # Try compiling the Java file using a random class name
        # The compilation will almost certainly fail, but we'll figure out the name of the main class.
        class_name = ''.join(random.choices(string.ascii_lowercase, k=8))
        sandbox.put_file(path_source, class_name + ".java")

        command = Compiler.COMPILE_COMMAND_JAVA.format(source=class_name + ".java")
        run_result = Runner.run_command(
            sandbox=sandbox, command=command, timeout=config.MAX_COMPILATION_TIME, print_stderr=True, privileged=True
        )

        # If the compilation *does not* fail then there is no public class in the file
        if run_result.exit_code == 0 and run_result.output.decode() == "":
            return "No public class provided."

        # If the compilation fails as we expect (the public class being named differently
        # than the file it is in), try again using the name the compiler gives us.
        if "is public, should be declared in a file named" in run_result.output.decode():
            class_name = run_result.output.decode().split(" is public")[0].split()[-1]
            sandbox.put_file(path_source, class_name + ".java")
            command = Compiler.COMPILE_COMMAND_JAVA.format(source=class_name + ".java")
            run_result = Runner.run_command(
                sandbox=sandbox, command=command, timeout=config.MAX_COMPILATION_TIME, print_stderr=True, privileged=True)

        # Check for standard errors (time limit, internal error or compilation error)
        if run_result.exec_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)
        if run_result.exit_code != 0:
            return "Compilation error: " + run_result.output.decode()

        # Do a sanity check that we have at least one class file with the public class
        if not sandbox.has_file(class_name + ".class"):
            return "An unexpected problem with the compilation arose, please report to the admin."

        # At this point everything seems to be fine and the code should be compiled into class files
        # Create a jar with them so we can execute it later on.
        command = Compiler.COMPILE_COMMAND_JAVA_JAR.format(class_name=class_name)
        run_result = Runner.run_command(
            sandbox=sandbox, command=command, timeout=config.MAX_COMPILATION_TIME, print_stderr=True, privileged=True)

        if run_result.exec_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)
        if run_result.exit_code != 0:
            return "Compilation error: " + run_result.output.decode()

        sandbox.get_file("result.jar", path_executable)
        return ""

    @staticmethod
    def compile_python(path_source, path_executable):
        name_source = os.path.basename(path_source)

        sandbox = Sandbox()
        sandbox.put_file(path_source, name_source)

        command = Compiler.COMPILE_COMMAND_PYTHON.format(source=name_source)
        run_result = Runner.run_command(
            sandbox=sandbox, command=command, timeout=config.MAX_COMPILATION_TIME, print_stderr=True, privileged=True)

        if run_result.output.decode() != "":
            return "Compilation error: " + run_result.output.decode()

        if run_result.exec_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        if run_result.exit_code != 0:
            return "Compilation exited with a non-zero exit code: {}".format(run_result.exit_code)

        # The file seems to be parsed correctly, so place it as an executable
        sandbox.get_file(name_source, path_executable)
        return ""
