import json
from threading import Timer, Lock
from time import time
from queue import Queue

import config
import common


class Updater:
    def __init__(self, endpoint, submit_id, tests):
        self.endpoint = endpoint
        self.submit_id = submit_id
        self.tests = tests
        self.last_update = -1e100
        self.scheduled_event = None
        self.queue = Queue()
        self.lock = Lock()

    def build_update(self):
        # Merge messages and results in the queue into a single update
        message, results = "", []
        while not self.queue.empty():
            cur_message, cur_results = self.queue.get()
            message = cur_message if cur_message != "" else message
            if cur_results is not None:
                for result in cur_results:
                    found = False
                    for i in range(len(results)):
                        if results[i]["id"] == result["id"]:
                            results[i] = result
                            found = True
                            break
                    if not found:
                        results.append(result)
        return message, results

    def update_frontend(self):
        self.lock.acquire()

        # Update the time of the last sent update
        self.last_update = time()

        # Cancel delayed update if any pending
        if self.scheduled_event is not None:
            self.scheduled_event.cancel()
            self.scheduled_event = None

        message, results = self.build_update()

        # Although quite unlikely, it is possible that the queue was empty
        # (For example, if this is a scheduled update, but a previous update already emptied the queue)
        if message != "" or len(results) > 0:
            data = {
                "id": self.submit_id,
                "message": message,
                "results": json.dumps(results),
                "timestamp": self.last_update
            }
            # We intentionally make the update synchronous so we're absolutely sure it is processed
            # before we send the next one (the lock ensures this).
            common.send_request("POST", self.endpoint, data)

        self.lock.release()

    def set_results(self, status):
        results = []
        next_id = 0
        for test in self.tests:
            results.append({
                "id": next_id,
                "position": test["position"],
                "status": status.name,
                "score": 0
            })
            next_id += 1
        return results

    def add_info(self, message="", results=None, status=None):
        # Build list of results for each test in case of mass status (P, T, C, IE, CE)
        if status is not None:
            results = self.set_results(status)

        # Put the update info in the update queue
        self.queue.put((message, results))

        # Check if the queue can be actually sent to the front-end
        # Update every UPDATE_INTERVAL seconds so we don't spam the frontend too much
        # We're using time() instead of perf_counter() so we get a UNIX timestamp (with parts of seconds)
        # This info helps figure out WHEN exactly (date + hour) the solution was graded.
        remaining_time = config.UPDATE_INTERVAL - (time() - self.last_update)
        if message != "" or remaining_time <= 0.0:
            self.update_frontend()
        # Otherwise schedule an update in the future (if not already scheduled)
        elif self.scheduled_event is None:
            self.scheduled_event = Timer(remaining_time, self.update_frontend)
            self.scheduled_event.start()
