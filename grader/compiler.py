"""
Compiles or parses source files and returns information if an error arises.
"""

import os
from shutil import copyfile

import config
from executor import Executor


class Compiler:
    COMPILE_LINE_CPP = "g++ -O2 -std=c++17 -w -s -o {path_executable} {path_source}"
    COMPILE_LINE_JAVA = "javac -nowarn -d {path_executable_dir} {path_source}"
    COMPILE_LINE_PYTHON = "python3 -m pyflakes {path_source}"

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
    def compile_cpp(path_source, path_executable):
        exit_code, stdout, stderr, compilation_time = Executor.cmd_exec(
            command=Compiler.COMPILE_LINE_CPP.format(path_executable=path_executable, path_source=path_source),
            timeout=config.MAX_COMPILATION_TIME
        )

        if stderr != "":
            return "Compilation error: " + stderr

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        if exit_code != 0:
            return "Compilation exited with a non-zero exit code: {}".format(exit_code)

        return ""

    @ staticmethod
    def cleanup_java_source(path_source):
        # Ignore package directives
        with open(path_source, "rt") as inp:
            lines_left = [line for line in inp.readlines() if not line.strip().startswith("package")]
        # # Make all classes public
        # for i in range(len(lines_left)):
        #     if lines_left[i].strip().startswith("class"):
        #         lines_left[i] = "public " + lines_left[i]
        with open(path_source, "wt") as out:
            out.writelines(lines_left)

    @staticmethod
    def compile_java(path_source, path_executable):
        # Remove "package" directives (we don't need them here)
        Compiler.cleanup_java_source(path_source)

        # Javac expects directory, not complete path
        name_executable = os.path.basename(path_executable)
        path_executable_dir = os.path.dirname(path_executable)
        class_name = os.path.basename(path_source).replace(".java", "")

        command = Compiler.COMPILE_LINE_JAVA.format(path_executable_dir=path_executable_dir, path_source=path_source)
        exit_code, stdout, stderr, compilation_time = Executor.cmd_exec(
            command=command,
            timeout=config.MAX_COMPILATION_TIME
        )

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        # The main class is not named properly.
        # Do a dirty fix copying the source to a temporary file with a proper name to handle that.
        if "is public, should be declared in a file named" in stderr:
            class_name = stderr.split(" is public")[0].split()[-1]
            path_source_new = os.path.join(os.path.dirname(path_source), class_name + ".java")
            copyfile(path_source, path_source_new)

            # Let's try this again...
            command = Compiler.COMPILE_LINE_JAVA.format(path_executable_dir=path_executable_dir, path_source=path_source_new)
            exit_code, stdout, stderr, compilation_time = Executor.cmd_exec(
                command=command,
                timeout=config.MAX_COMPILATION_TIME
            )

        if stderr != "":
            return "Compilation error: " + stderr

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        if exit_code != 0:
            return "Compilation exited with a non-zero exit code: {}".format(exit_code)

        # Create a jar with the compiled class file(s)
        # If there is no class file, there was some problem with the compilation (e.g. empty source)
        if os.path.exists(os.path.join(path_executable_dir, class_name + ".class")):
            manifest_file = os.path.join(path_executable_dir, "manifest.mf")
            jar_file = os.path.join(path_executable_dir, name_executable)

            with open(manifest_file, "wt") as manifest:
                manifest.write("Manifest-version: 1.0\n")
                manifest.write("Main-Class: {}\n".format(class_name))

            command = "jar cfm {} {} -C {}/ .".format(jar_file, manifest_file, path_executable_dir)
            exit_code, stdout, stderr, compilation_time = Executor.cmd_exec(command, config.MAX_COMPILATION_TIME)
        else:
            stderr = "Empty or buggy file provided."

        return stderr

    @staticmethod
    def compile_python(path_source, path_executable):
        exit_code, stdout, stderr, compilation_time = Executor.cmd_exec(
            command=Compiler.COMPILE_LINE_PYTHON.format(path_source=path_source),
            timeout=config.MAX_COMPILATION_TIME
        )

        # Pyflakes prints its output to stdout instead of stderr
        if stdout != "":
            return "Compilation error: " + stdout

        if compilation_time > config.MAX_COMPILATION_TIME:
            return "Compilation exceeded the time limit of {0:.2f} seconds.".format(config.MAX_COMPILATION_TIME)

        # The file seems to be parsed correctly, so place it as an executable
        copyfile(path_source, path_executable)
        return ""

