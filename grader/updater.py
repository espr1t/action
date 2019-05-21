import json
from threading import Timer, Lock
from time import time

import config
import common


class Updater:
    def __init__(self, endpoint, submit_id, tests):
        self.endpoint = endpoint
        self.submit_id = submit_id
        self.tests = tests
        self.last_update = 0
        self.scheduled_event = None
        self.updated = False
        self.message = ""
        self.results = []
        self.lock = Lock()

    def apply_update(self, message, results):
        if message != "":
            self.message = message
            self.updated = True
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
                self.updated = True

    def update_frontend(self):
        # Make sure only one update is happening at a time
        self.lock.acquire()

        # Indicate that there is no scheduled update
        self.scheduled_event = None

        # Only send updates if there was some new information
        if self.updated:
            # Update the time of the last sent update
            self.last_update = time()

            # Actually send the update
            common.send_request("POST", self.endpoint, {
                "id": self.submit_id,
                "message": self.message,
                "results": json.dumps(self.results),
                "timestamp": self.last_update
            })

            # Mark that there is no new information
            self.updated = False

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
        self.lock.acquire()

        # Build list of results for each test in case of mass status (P, T, C, IE, CE)
        if status is not None:
            results = self.set_results(status)

        # Put the update info in the update queue
        self.apply_update(message, results)

        # Update every UPDATE_INTERVAL seconds (except if a special update) so we don't spam the frontend too much
        # We're using time() instead of perf_counter() so we get a UNIX timestamp (with parts of seconds)
        # This info helps figure out WHEN exactly (date + hour) the solution was graded.
        # If no event is scheduled yet, do so.
        if self.scheduled_event is None:
            remaining_time = max(0.0, config.UPDATE_INTERVAL - (time() - self.last_update))
            self.scheduled_event = Timer(remaining_time, self.update_frontend)
            self.scheduled_event.start()

        self.lock.release()
