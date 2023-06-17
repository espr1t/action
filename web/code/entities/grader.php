<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");
require_once(__DIR__ . "/problem.php");
require_once(__DIR__ . "/submit.php");
require_once(__DIR__ . "/match.php");

class Grader {
    private static string $METHOD_GET = "GET";
    private static string $METHOD_POST = "POST";

    static function call(string $path, array $data, string $method, bool $json=true, int $timeout=3): array {
        $curl = curl_init();

        // Set up the connection
        if ($method == Grader::$METHOD_POST) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, array("data" => json_encode($data)));
        }

        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $GLOBALS["GRADER_URL"] . $path);

        // Setup authentication
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $hashedUsername = sha1($GLOBALS["GRADER_USERNAME"]);
        $hashedPassword = sha1($GLOBALS["GRADER_PASSWORD"]);
        curl_setopt($curl, CURLOPT_USERPWD, "{$hashedUsername}:{$hashedPassword}");

        // Execute the request
        $response = curl_exec($curl);

        // Convert the response to array (no matter whether successful or not)
        if ($response === false) {
            $response = array();
        } else {
            if ($json) {
                $response = json_decode($response, true);
            } else {
                $response = array("data" => $response);
            }
        }

        // Attach the HTTP status code to the response
        $response["status"] = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close the connection and return
        curl_close($curl);
        return $response;
    }

    static function available(): bool {
        $response = Grader::call($GLOBALS["GRADER_ENDPOINT_AVAILABLE"], [], Grader::$METHOD_GET);
        return $response["status"] == 200;
    }

    static function recent(): ?array {
        $response = Grader::call($GLOBALS["GRADER_ENDPOINT_RECENT"], [], Grader::$METHOD_GET);
        return $response["status"] != 200 ? null : $response["submits"];
    }

    static function evaluate(Submit $submit): bool {
        // Record invoking this test run in the logs
        write_log($GLOBALS["LOG_SUBMITS"], "Sending submit {$submit->getId()} for grading...");

        $response = Grader::call($GLOBALS["GRADER_ENDPOINT_EVALUATE"], $submit->getGradingData(), Grader::$METHOD_POST);
        return $response["status"] == 200;
    }

    private function updateTest(Submit $submit, int $test, float $score, float $execTime, float $execMemory): void {
        // TODO: Figure out why we are appending to the result (adding and getting the max) instead of overwriting it.
        // Update the test score
        if (!is_numeric($submit->getResults()[$test])) {
            $submit->setResult($test, "0");
        }
        $submit->setResult($test, sprintf("%.3f", floatval($submit->getResults()[$test]) + $score));
        // Update the test execution time (we've already checked that the match has ended, so it should be set)
        $submit->setExecTime($test, max($submit->getExecTime()[$test], $execTime));
        // Update the test execution memory (we've already checked that the match has ended, so it should be set)
        $submit->setExecMemory($test, max($submit->getExecMemory()[$test], $execMemory));
        $submit->update();
    }

    private function updateGameTest(Submit $submit, array $result): void {
        // Update the information about this match
        $test = getIntValue($result, "position");
        $userOne = getIntValue($result, "player_one_id");
        $userTwo = getIntValue($result, "player_two_id");
        $scoreOne = getFloatValue($result, "player_one_score");
        $scoreTwo = getFloatValue($result, "player_two_score");
        $execTimeP1 = getFloatValue($result, "player_one_exec_time");
        $execTimeP2 = getFloatValue($result, "player_two_exec_time");
        $execMemoryP1 = getFloatValue($result, "player_one_exec_memory");
        $execMemoryP2 = getFloatValue($result, "player_two_exec_memory");

        $match = Match::get($submit->getProblemId(), $test, $userOne, $userTwo);
        $match->setScoreOne($scoreOne);
        $match->setScoreTwo($scoreTwo);
        $match->setMessage(getStringValue($result, "message"));
        $match->setReplayKey(getStringValue($result, "replay_key"));
        $match->update();

        // Update the information about this test in the user's submit
        $score = $submit->getUserId() == $userOne ? $match->getScoreOne() : $match->getScoreTwo();
        $execTime = $submit->getUserId() == $userOne ? $execTimeP1 : $execTimeP2;
        $execMemory = $submit->getUserId() == $userOne ? $execMemoryP1 : $execMemoryP2;
        $this->updateTest($submit, $test, $score, $execTime, $execMemory);

        // Update the information about this test in the opponent's submit
        $opponentSubmitId = $submit->getUserId() == $userOne ? $match->getSubmitTwo() : $match->getSubmitOne();
        if ($opponentSubmitId > 0) {
            $opponentSubmit = Submit::get($opponentSubmitId);
            if ($opponentSubmit == null) {
                error_log("Couldn't find opponent's submit with id {$opponentSubmitId}, although should be present!");
            } else {
                $score = $submit->getUserId() != $userOne ? $match->getScoreOne() : $match->getScoreTwo();
                $execTime = $submit->getUserId() != $userOne ? $execTimeP1 : $execTimeP2;
                $execMemory = $submit->getUserId() != $userOne ? $execMemoryP1 : $execMemoryP2;
                $this->updateTest($opponentSubmit, $test, $score, $execTime, $execMemory);
            }
        }
    }

    private function updateTaskTest(Submit $submit, array $result): void {
        $position = getIntValue($result, "position");
        $score = getFloatValue($result, "score");
        $status = getStringValue($result, "status");
        $execTime = array_key_exists("exec_time", $result) ? getFloatValue($result, "exec_time") : null;
        $execMemory = array_key_exists("exec_memory", $result) ? getFloatValue($result, "exec_memory") : null;
        $info = array_key_exists("info", $result) ? getStringValue($result, "info") : null;
        $replayKey = array_key_exists("replay_key", $result) ? getStringValue($result, "replay_key") : null;

        // Update the status of a single test
        if ($status == "ACCEPTED") {
            $submit->setResult($position, sprintf("%.3f", $score));
        } else {
            $submit->setResult($position, $GLOBALS["STATUS_{$status}"]);
        }
        // Update the execution time (if provided, can be missing if in state WAITING, COMPILING, or TESTING)
        if ($execTime !== null) {
            $submit->setExecTime($position, $execTime);
        }
        // Update the execution memory (if provided, can be missing if in state WAITING, COMPILING, or TESTING)
        if ($execMemory !== null) {
            $submit->setExecMemory($position, $execMemory);
        }
        // Update the info fields for tests other than the sample
        if ($info) {
            $submitInfo = explode(",", $submit->getInfo());
            if (count($submitInfo) != count($submit->getResults())) {
                $submitInfo = array_fill(0, count($submit->getResults()), "");
            }
            $submitInfo[$position] = $info;
            $submit->setInfo(implode(",", $submitInfo));
        }
        // Update the replayKey for the test (only for problems with testers)
        if ($replayKey) {
            $submitReplayKeys = explode(",", $submit->getReplayKey());
            if (count($submitReplayKeys) != count($submit->getResults())) {
                $submitReplayKeys = array_fill(0, count($submit->getResults()), "");
            }
            $submitReplayKeys[$position] = $replayKey;
            $submit->setReplayKey(implode(",", $submitReplayKeys));
        }
    }

    function update(int $submitId, string $message, array $results, float $timestamp): void {
        $submit = Submit::get($submitId);
        if ($submit == null) {
            error_log("WARNING: Received update on invalid submit: {$submitId}");
            exit();
        }

        // If already updated, skip this update
        // NOTE: This logic still imposes some small risk of a race condition
        //       (between this check and the $submit->update() call bellow).
        if ($submit->getGradingFinish() > $timestamp) {
            error_log("INFO: Skipping update on submit {$submitId} with message \"{$message}\": " .
                "requested update for {$timestamp}, but already at {$submit->getGradingFinish()}.");
            exit();
        }
        // Update the newest timestamp in the database
        $submit->setGradingFinish($timestamp);
        $submit->update();

        $submit->setMessage($message);
        $problem = Problem::get($submit->getProblemId());
        foreach ($results as $result) {
            // If a game and the match is already played, update the scoreboard and its info in the database
            if ($problem->getType() == "game" && array_key_exists("player_one_id", $result)) {
                $this->updateGameTest($submit, $result);
            }
            // If a standard task or a match which has not yet been played, just update the test status
            else {
                $this->updateTaskTest($submit, $result);
            }
        }
        // Update the status in the database
        $submit->setStatus($submit->calcStatus());
        $submit->update();

        // Update the submit history
        if ($submit->getMessage() != "") {
            $history = Brain::getHistory($submit->getId());
            if ($history == null) {
                // If we still haven't added history for this submit
                Brain::addHistory($submit->getId());
                $history = Brain::getHistory($submit->getId());
            }
            $history["time01"] = $history["time02"];
            $history["time02"] = $history["time03"];
            $history["time03"] = $history["time04"];
            $history["time04"] = $history["time05"];
            $history["time05"] = implode(",", $submit->getExecTime());
            Brain::updateHistory($submit->getId(), $history);
        }

        // Record the completed test run in the logs and update the queue
        // (This potentially sends the next unprocessed submit to the grader.)
        if ($submit->getMessage() != "") {
            write_log($GLOBALS["LOG_SUBMITS"], "Submit {$submit->getId()} has been processed.");
            Queue::update();
        }
    }

    static function print_to_pdf(string $url): ?string {
        $response = Grader::call(
            $GLOBALS["GRADER_ENDPOINT_PRINT_PDF"], array("url" => $url), Grader::$METHOD_POST, false, 10
        );
        return $response["status"] != 200 ? null : $response["data"];
    }

    static function get_replay(string $replayKey): ?string {
        $response = Grader::call(
            $GLOBALS["GRADER_ENDPOINT_GET_REPLAY"], array("key" => $replayKey), Grader::$METHOD_POST, false
        );
        return $response["status"] != 200 ? null : $response["data"];
    }
}

?>
