<?php
require_once('grader.php');


class Queue {
    static function getPending() {
        return Submit::getPendingSubmits();
    }

    static function getLatest() {
        return Submit::getLatestSubmits();
    }

    static function update() {
        // Checks if some of the pending submits in the queue can be sent to the grader for testing.
        // If the grader has less than GRADER_MAX_WAITING_SUBMITS additional ones are being sent.
        // Triggered on:
        //     1. Submit
        //     2. After the testing of a submit is completed
        //     3. By the scheduled cron job (each minute)
        //     4. By an optional update (see below)

        $fp = fopen(__DIR__ . '/queue.lock', 'r');
        if (flock($fp, LOCK_EX)) {
            // Get the pending submits
            $pendingSubmits = Queue::getPending();
            if (count($pendingSubmits) <= 0) {
                // No pending submits, thus nothing to do
                flock($fp, LOCK_UN);
                fclose($fp);
                return;
            }

            // Get the recently graded submits
            $recentKeys = Grader::recent();
            if ($recentKeys === null) {
                // Grader is not available, thus not much we can do at the moment
                flock($fp, LOCK_UN);
                fclose($fp);
                return;
            }

            // Calculate how many are currently graded or waiting on the Grader
            $numWaitingSubmits = 0;
            foreach ($pendingSubmits as $submit) {
                if (in_array($submit->getKey(), $recentKeys)) {
                    $numWaitingSubmits++;
                }
            }
            // Fill the number up to GRADER_MAX_WAITING_SUBMITS with pending submits
            foreach ($pendingSubmits as $submit) {
                if (!in_array($submit->getKey(), $recentKeys)) {
                    if ($numWaitingSubmits < $GLOBALS['GRADER_MAX_WAITING_SUBMITS']) {
                        $numWaitingSubmits++;
                        Grader::evaluate($submit);
                    }
                }
            }

            // Release the lock after everything's done
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

}

?>
