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
        self.last_update = 0
        self.scheduled_event = None
        self.message = ""
        self.results = []
        self.queue = Queue()
        self.lock = Lock()

    def apply_updates(self):
        # Merge messages and results in the queue into a single update
        applied_updates = False
        while not self.queue.empty():
            applied_updates = True
            message, results = self.queue.get()
            if message != "":
                self.message = message
            if results is not None:
                for result in results:
                    found = False
                    for i in range(len(self.results)):
                        # We could have used test["position"] for this, if it wasn't for the games. There we
                        # have two matches with the same test["position"] (one as first and one as second player)
                        if self.results[i]["id"] == result["id"]:
                            self.results[i] = result
                            found = True
                            break
                    if not found:
                        self.results.append(result)
        return applied_updates

    def update_frontend(self):
        # Make sure only one update is happening at a time
        self.lock.acquire()

        # Update the time of the last sent update
        self.last_update = time()

        # Cancel delayed update if any pending
        if self.scheduled_event is not None:
            self.scheduled_event.cancel()
        # No matter if we just cancelled a pending event or this thread is the event itself,
        # make it None so we can schedule a new one later on
        self.scheduled_event = None

        # Only send updates if there was some new information
        if self.apply_updates():
            common.send_request("POST", self.endpoint, {
                "id": self.submit_id,
                "message": self.message,
                "results": json.dumps(self.results),
                "timestamp": self.last_update
            })

        self.lock.release()

    def set_results(self, status):
        results = []
        for result_id in range(len(self.tests)):
            results.append({
                "id": result_id,
                "position": self.tests[result_id]["position"],
                "status": status.name,
                "score": 0
            })
        return results

    def add_info(self, message="", results=None, status=None):
        # Build list of results for each test in case of mass status (P, T, C, IE, CE)
        if status is not None:
            results = self.set_results(status)

        # Put the update info in the update queue
        self.queue.put((message, results))

        # Update every UPDATE_INTERVAL seconds (except if a special update) so we don't spam the frontend too much
        # We're using time() instead of perf_counter() so we get a UNIX timestamp (with parts of seconds)
        # This info helps figure out WHEN exactly (date + hour) the solution was graded.
        remaining_time = config.UPDATE_INTERVAL - (time() - self.last_update)
        if message != "" or remaining_time <= 0.0:
            self.update_frontend()
        # Otherwise schedule an update in the future (if not already scheduled)
        elif self.scheduled_event is None:
            self.scheduled_event = Timer(remaining_time, self.update_frontend)
            self.scheduled_event.start()
