"""
Executes a binary in a sandbox environment limiting its resources.
It limits the time and memory consumption, hard disk access, network access, thread/process creation.
Uses Docker containers as a sandbox (slow but secure; also relatively cross-platform solution)
"""

import os
import subprocess
from signal import SIGKILL
from time import sleep, perf_counter

import docker
import docker.errors
import docker.types
from docker.utils.socket import frames_iter, consume_socket_output, demux_adaptor

import common
import config

# Assumes we are currently in the docker folder (/action/grader/docker)
# DOCKER_BUILD_COMMAND = "docker build --tag=action_sandbox --file action_sandbox.docker ."

# DOCKER_RUN_COMMAND = "docker run"
# DOCKER_RUN_COMMAND += " --name {container}"                 # Set the name of the container
# DOCKER_RUN_COMMAND += " --ipc private"                      # Limit the shared memory namespace
# DOCKER_RUN_COMMAND += " --network none"                     # Disable the network
# DOCKER_RUN_COMMAND += " --restart unless-stopped"           # Restart the container on fail or exit
# DOCKER_RUN_COMMAND += " --security-opt no-new-privileges"   # Prevent the processes to gain additional privileges
# DOCKER_RUN_COMMAND += " --memory 2048M"                     # Limit the total container memory to 2GB
# DOCKER_RUN_COMMAND += " --kernel-memory 1024M"              # Allow the kernel at most 1GB of the memory
# DOCKER_RUN_COMMAND += " --memory-swap 2048M"                # Disable the swap (use only memory)
# DOCKER_RUN_COMMAND += " --memory-swappiness 0"              # Disable the swap (in a second way)
# DOCKER_RUN_COMMAND += " --cpus 1"                           # Allow using only a single core
# DOCKER_RUN_COMMAND += " --detach"                           # Run container in the background
# DOCKER_RUN_COMMAND += " --tty"                              # Allocate a pseudo-TTY
# DOCKER_RUN_COMMAND += " --interactive"                      # Keep STDIN open
# DOCKER_RUN_COMMAND += " --ulimit nproc=20"                  # Limit the number of processes
# DOCKER_RUN_COMMAND += " --ulimit nofile=7"                  # Limit the number of open extra files
# DOCKER_RUN_COMMAND += " --ulimit stack=67108864"            # Limit the stack size to 64MB
# DOCKER_RUN_COMMAND += " --ulimit fsize=16777216"            # Limit the maximum output to 16MB
# DOCKER_RUN_COMMAND += " --ulimit msgqueue=0"                # Disallow message queues
# DOCKER_RUN_COMMAND += " --ulimit core=0"                    # Disallow creation of core dump files
# DOCKER_RUN_COMMAND += " --ulimit data=1073741824"           # Limit the max memory the process can use to 1GB
# DOCKER_RUN_COMMAND += " action_sandbox"                     # Image name

# Note: add --interactive to be able to pipe stdin
# DOCKER_EXEC_COMMAND = "docker exec --user {user} --workdir {workdir} {container} /bin/bash -c \"{command}\""
# DOCKER_COPY_COMMAND = "docker cp {file} {container}:/home/{container}/{name}"


docker_client = docker.from_env()
logger = common.get_logger(__name__)


class Executor:
    @staticmethod
    def setup_containers(num_workers):
        for sandbox_id in range(1, num_workers + 1):
            container_id = "sandbox{:02d}".format(sandbox_id)

            try:
                container = docker_client.containers.get(container_id=container_id)
                if container.status == "running":
                    logger.info("Container {container_id} already running.".format(container_id=container_id))
                else:
                    logger.info("Container {container_id} was stopped. Restarting...".format(container_id=container_id))
                    container.restart()

            except docker.errors.NotFound:
                logger.info("Spinning up container {container_id}.".format(container_id=container_id))
                container = docker_client.containers.create(
                    image="action_sandbox",
                    name=container_id,
                    user=container_id,
                    detach=True,
                    tty=True,
                    stdin_open=True,
                    ipc_mode="private",
                    network_disabled=True,
                    restart_policy={"Name": "on-failure", "MaximumRetryCount": 3},
                    security_opt=["no-new-privileges"],
                    mem_limit="2G",
                    memswap_limit="2G",
                    kernel_memory="1G",
                    mem_swappiness=0,
                    cpu_period=100000,
                    cpu_quota=100000,
                    ulimits=[
                        docker.types.Ulimit(name="nproc", soft=20, hard=20),
                        docker.types.Ulimit(name="stack", soft=67108864, hard=67108864),
                        docker.types.Ulimit(name="fsize", soft=16777216, hard=16777216),
                        docker.types.Ulimit(name="data", soft=1073741824, hard=1073741824),
                        docker.types.Ulimit(name="msgqueue", soft=0, hard=0),
                        docker.types.Ulimit(name="core", soft=0, hard=0),
                        docker.types.Ulimit(name="nice", soft=-20, hard=-20)
                    ]
                )
                container.start()
            common.containers.put(container)

    @staticmethod
    def cmd_exec(command, timeout=config.MAX_EXECUTION_TIME):
        start_time = perf_counter()
        process = subprocess.Popen(
            args=command,
            shell=True,
            stdin=None,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            universal_newlines=True
        )
        while True:
            sleep(0.1)

            # Process already terminated
            exit_code = process.poll()
            if exit_code is not None:
                break

            # Execution is taking too much time
            if perf_counter() - start_time > timeout:
                # Kill the shell and the compilation process
                os.kill(process.pid, SIGKILL)
                break

        output = process.communicate()
        stdout = output[0] if output[0] is not None else ""
        stderr = output[1] if output[1] is not None else ""
        return exit_code, stdout, stderr, perf_counter() - start_time

    @staticmethod
    def docker_exec(container, command, user, workdir):
        _, output = container.exec_run(cmd=command, user=user, workdir=workdir, demux=True)
        stdout = output[0].decode() if output[0] is not None else ""
        stderr = output[1].decode() if output[1] is not None else ""
        return stdout, stderr

    @staticmethod
    def docker_exec_with_stdio(container, command, user, workdir, input_data):
        _, socket = container.exec_run(cmd=command, user=user, workdir=workdir, stdin=True, socket=True)
        socket._writing = True
        socket._sock.sendall(input_data.encode())
        # input_data = input_data.encode()
        # while len(input_data) > 0:
        #     written = socket.write(input_data)
        #     input_data = input_data[written:]
        socket.flush()

        gen = frames_iter(socket, False)
        gen = (demux_adaptor(*frame) for frame in gen)
        output = consume_socket_output(gen, True)

        stdout = output[0].decode() if output[0] is not None else ""
        stderr = output[1].decode() if output[1] is not None else ""
        print("STDOUT: {}".format(stdout))
        print("STDERR: {}".format(stderr))

        socket.close()
        return stdout, stderr

