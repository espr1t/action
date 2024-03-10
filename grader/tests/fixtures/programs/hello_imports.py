import os
import sys
import threading
import logging
import signal


def hello():
    print("Hello, World!")


print(os.getuid(), file=sys.stderr)

thread = threading.Thread(target=hello)
thread.start()
thread.join()

logger = logging.getLogger()
logger.info("Hell, Yeah!")

print(signal.SIGKILL, file=sys.stderr)
