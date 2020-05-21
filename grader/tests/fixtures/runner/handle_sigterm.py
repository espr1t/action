import sys
import signal
from threading import Thread
from time import perf_counter, sleep


def handle_sigterm(signum, frame):
    print("Got SIGTERM signal.")


def dowork():
    start_time = perf_counter()
    sleep(0.3)
    print("{:.3f}s".format(perf_counter() - start_time))


if __name__ == "__main__":
    if "--handle" in sys.argv:
        signal.signal(signal.SIGTERM, handle_sigterm)

    thread = Thread(target=dowork)
    thread.start()
    thread.join()
