<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/problem.php');
require_once(__DIR__ . '/submit.php');
require_once(__DIR__ . '/match.php');

class Grader {
    private static $METHOD_GET = 'GET';
    private static $METHOD_POST = 'POST';

    static function call($path, $data, $method, $json=true) {
        $curl = curl_init();

        // Setup the connection
        if ($method == Grader::$METHOD_POST) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, array('data' => json_encode($data)));
        }

        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $GLOBALS['GRADER_URL'] . $path);

        // Setup authentication
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $hashedUsername = sha1($GLOBALS['GRADER_USERNAME']);
        $hashedPassword = sha1($GLOBALS['GRADER_PASSWORD']);
        curl_setopt($curl, CURLOPT_USERPWD, $hashedUsername . ':' . $hashedPassword);

        // Execute the request
        $response = curl_exec($curl);

        // Convert the response to array (no matter whether successful or not)
        if ($response === false) {
            $response = [];
        } else {
            if ($json) {
                $response = json_decode($response, true);
            } else {
                $response = array('data' => $response);
            }
        }

        // Attach the HTTP status code to the response
        $response['status'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close the connection and return
        curl_close($curl);
        return $response;
    }

    static function available() {
        $response = self::call($GLOBALS['GRADER_ENDPOINT_AVAILABLE'], [], Grader::$METHOD_GET);
        return $response['status'] == 200;
    }

    static function recent() {
        $response = self::call($GLOBALS['GRADER_ENDPOINT_RECENT'], [], Grader::$METHOD_GET);
        return $response['status'] != 200 ? null : $response['submits'];
    }

    static function evaluate(Submit $submit) {
        // Record invoking this test run in the logs
        write_log($GLOBALS['LOG_SUBMITS'], sprintf('Sending submit %d for grading...', $submit->id));

        $response = self::call($GLOBALS['GRADER_ENDPOINT_EVALUATE'], $submit->getGradingData(), Grader::$METHOD_POST);
        return $response['status'] == 200;
    }

    private function updateTest($submit, $test, $score, $execTime, $execMemory) {
        // Update the test score
        if (!is_numeric($submit->results[$test]))
            $submit->results[$test] = 0;
        $submit->results[$test] += $score;
        // Update the test execution time (we've already checked that the match has ended, so it should be set)
        $submit->execTime[$test] = max($submit->execTime[$test], $execTime);
        // Update the test execution memory (we've already checked that the match has ended, so it should be set)
        $submit->execMemory[$test] = max($submit->execMemory[$test], $execMemory);
        $submit->update();
    }

    private function updateGameTest($submit, $result) {
        // Update the information about this match
        $test = intval($result['position']);
        $userOne = intval($result['player_one_id']);
        $userTwo = intval($result['player_two_id']);
        $match = Match::get($submit->problemId, $test, $userOne, $userTwo);
        $match->scoreOne = floatval($result['player_one_score']);
        $match->scoreTwo = floatval($result['player_two_score']);
        $match->message = $result['message'];
        $match->log = $result['match_log'];
        $match->update();

        // Update the information about this test in the user's submit
        $score = $submit->userId == $userOne ? $match->scoreOne : $match->scoreTwo;
        $execTime = floatval($submit->userId == $userOne ? $result['player_one_exec_time'] : $result['player_two_exec_time']);
        $execMemory = floatval($submit->userId == $userOne ? $result['player_one_exec_memory'] : $result['player_two_exec_memory']);
        $this->updateTest($submit, $test, $score, $execTime, $execMemory);

        // Update the information about this test in the opponent's submit
        $opponentSubmitId = $submit->userId == $userOne ? $match->submitTwo : $match->submitOne;
        if ($opponentSubmitId > 0) {
            $opponentSubmit = Submit::get($opponentSubmitId);
            if ($opponentSubmit == null) {
                error_log('Couldn\'t find opponent\'s submit with id ' . $opponentSubmitId . ', although should be present!');
            } else {
                $score = $submit->userId != $userOne ? $match->scoreOne : $match->scoreTwo;
                $execTime = floatval($submit->userId != $userOne ? $result['player_one_exec_time'] : $result['player_two_exec_time']);
                $execMemory = floatval($submit->userId != $userOne ? $result['player_one_exec_memory'] : $result['player_two_exec_memory']);
                $this->updateTest($opponentSubmit, $test, $score, $execTime, $execMemory);
            }
        }
    }

    private function updateTaskTest($submit, $result) {
        // Update the status of a single test
        if ($result['status'] == 'ACCEPTED') {
            $submit->results[$result['position']] = floatval($result['score']);
        } else {
            $submit->results[$result['position']] = $GLOBALS['STATUS_' . $result['status']];
        }
        // Update the execution time (if provided, can be missing if in state WAITING, COMPILING, or TESTING)
        if (array_key_exists('exec_time', $result)) {
            $submit->execTime[$result['position']] = floatval($result['exec_time']);
        }
        // Update the execution memory (if provided, can be missing if in state WAITING, COMPILING, or TESTING)
        if (array_key_exists('exec_memory', $result)) {
            $submit->execMemory[$result['position']] = floatval($result['exec_memory']);
        }
        // Update the info fields for tests other than the sample
        if (array_key_exists('info', $result) && $result['info'] != '') {
            $results_info = explode(',', $submit->info);
            if (count($results_info) != count($submit->results)) {
                $results_info = array_fill(0, count($submit->results), '');
            }
            $results_info[$result['position']] = $result['info'];
            $submit->info = implode(',', $results_info);
        }
    }

    function update($submitId, $message, $results, $timestamp) {
        $submit = Submit::get($submitId);
        if ($submit == null) {
            error_log('Received update on invalid submit: ' . $submitId);
            exit();
        }

        // If already updated, skip this update
        // NOTE: This logic still imposes some small risk of a race condition
        // (between this check and the updateSubmit() call bellow)
        if ($submit->gradingFinish > $timestamp) {
            error_log(sprintf('Skipping update on submit %d with message "%s": requested update for %f, but already at %f.',
                    $submitId, $message, $timestamp, $submit->gradingFinish));
            exit();
        }
        // Update the timestamp of the latest submit
        $submit->gradingFinish = $timestamp;

        $brain = new Brain();
        $brain->updateSubmit($submit);

        $submit->message = $message;
        $problem = Problem::get($submit->problemId);
        foreach ($results as $result) {
            // If a game and the match is already played, update the scoreboard and its info in the database
            if ($problem->type == 'game' && array_key_exists('player_one_id', $result)) {
                $this->updateGameTest($submit, $result);
            }
            // If a standard task or a match which has not yet been played, just update the test status
            else {
                $this->updateTaskTest($submit, $result);
            }
        }
        $submit->status = $submit->calcStatus();

        // Save the updated submit info in the database
        $brain->updateSubmit($submit);

        // Update the submit history
        if ($submit->message != '') {
            $history = $brain->getHistory($submit->id);
            if ($history == null) {
                // If we still haven't added history for this submit
                $brain->addHistory($submit->id);
                $history = $brain->getHistory($submit->id);
            }
            $history['time01'] = $history['time02'];
            $history['time02'] = $history['time03'];
            $history['time03'] = $history['time04'];
            $history['time04'] = $history['time05'];
            $history['time05'] = implode(',', $submit->execTime);
            $brain->updateHistory($submit->id, $history);
        }

        // Record the completed test run in the logs
        if ($submit->message != '') {
            $logMessage = sprintf('Submit %d has been processed.', $submit->id);
            write_log($GLOBALS['LOG_SUBMITS'], $logMessage);
            Queue::update();
        }
    }

    function print_to_pdf($url) {
        $response = $this->call($GLOBALS['GRADER_ENDPOINT_PRINT_PDF'], array("url" => $url), Grader::$METHOD_POST, false);
        if ($response['status'] != 200) {
            // Try a second time?
            $response = $this->call($GLOBALS['GRADER_ENDPOINT_PRINT_PDF'], array("url" => $url), Grader::$METHOD_POST, false);
            if ($response['status'] != 200) {
                return null;
            }
        }
        return $response['data'];
    }

    function get_replay($replayId) {
        $response = $this->call($GLOBALS['GRADER_ENDPOINT_GET_REPLAY'], array("id" => $replayId), Grader::$METHOD_POST, false);
        return $response['status'] == 200 ? $response['data'] : null;
    }
}

?>
