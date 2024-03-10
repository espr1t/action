import sys
import signal
from threading import Thread
from time import sleep


def handle_sigterm(signum, frame):
    print("Got SIGTERM signal.")


def dowork():
    sleep(0.2)
    print("The sleeper has awakened.")


if __name__ == "__main__":
    if "--handle" in sys.argv:
        signal.signal(signal.SIGTERM, handle_sigterm)

    thread = Thread(target=dowork)
    thread.start()
    thread.join()
