from os import path
from io import BytesIO
import tarfile

import common
from executor import Executor

logger = common.get_logger(__name__)


class Sandbox:
    def __init__(self):
        # Get any available container
        self.container = common.containers.get()
        # Get the container name
        self.container_id = self.container.name
        # Clean the sandbox directory
        self.clean()

    def __del__(self):
        common.containers.put(self.container)

    def get(self, file_path):
        stream, info = self.container.get_archive(file_path)
        temp = BytesIO()
        for i in stream:
            temp.write(i)
        temp.seek(0)
        archive = tarfile.open(mode="r", fileobj=temp)
        data = archive.extractfile(path.basename(file_path)).read().decode()
        archive.close()
        return data

    def put(self, file_list):
        # Puts files (e.g., executable, input and output files) in the sandbox directory
        # The argument file_list is a list of tuples (path_to_file, file_name_on_sandbox)
        temp = BytesIO()
        archive = tarfile.open(mode="w:gz", fileobj=temp)

        for file_path_name in file_list:
            with open(file_path_name[0], "rb") as file_obj:
                file_info = archive.gettarinfo(file_path_name[0], file_path_name[1])
                file_info.uid = file_info.gid = 1000 + int(self.container.name[7:]) - 1
                file_info.uname = file_info.gname = self.container.name
                archive.addfile(file_info, file_obj)

        archive.close()
        return self.container.put_archive("/sandbox/", temp.getvalue())

    def clean(self):
        # Cleans the sandbox directory
        command = "/bin/bash -c \"rm -rf /sandbox/*\""
        stdout, stderr = Executor.docker_exec(
            container=self.container, command=command, user="root", workdir=None
        )
        if stdout != "" or stderr != "":
            logger.error(
                "Could not clean sandbox directory on container {container}! Message was: {message}".format(
                    container=self.container.name, message=stdout + stderr)
            )
            raise Exception("Could not clean sandbox dir for container {}!".format(self.container_id))
