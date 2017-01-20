<?php
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/problem.php');
require_once(__DIR__ . '/submit.php');

class Grader {
    private static $METHOD_GET = 'GET';
    private static $METHOD_POST = 'POST';

    function call($path, $data, $method) {
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
        if (!$response) {
            $response = [];
        } else {
            $response = json_decode($response, true);
        }

        // Attach the HTTP status code to the response
        $response['status'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close the connection and return
        curl_close($curl);
        return $response;
    }

    function available() {
        $response = $this->call($GLOBALS['GRADER_ENDPOINT_AVAILABLE'], [], Grader::$METHOD_GET);
        return $response['status'] == 200;
    }

    function evaluate($data) {
        $response = $this->call($GLOBALS['GRADER_ENDPOINT_EVALUATE'], $data, Grader::$METHOD_POST);
        return true;
    }

    function update($submitId, $message, $results, $timestamp) {
        $submit = Submit::get($submitId);
        if ($submit == null) {
            error_log('Received update on invalid submit: ' . $submitId);
            exit();
        }

        // If already updated, skip it
        if ($submit->graded > $timestamp) {
            error_log(sprintf(
                'Skipping update: requested update for %f, but already at %f.', $timestamp, $submit->graded));
            return;
        }
        $submit->graded = $timestamp;

        // Add new information about individual tests
        $submit->message = $message;
        for ($i = 0; $i < count($results); $i = $i + 1) {
            // Update the status of the test
            if ($results[$i]['status'] == 'ACCEPTED') {
                $submit->results[$results[$i]['position']] = floatval($results[$i]['score']);
            } else {
                $submit->results[$results[$i]['position']] = $GLOBALS['STATUS_' . $results[$i]['status']];
            }
            // Update the execution time (if provided, can be missing if in state WAITING, COMPILING, or TESTING)
            if (array_key_exists('exec_time', $results[$i])) {
                $submit->exec_time[$results[$i]['position']] = floatval($results[$i]['exec_time']);
            }
            // Update the execution memory (if provided, can be missing if in state WAITING, COMPILING, or TESTING)
            if (array_key_exists('exec_memory', $results[$i])) {
                $submit->exec_memory[$results[$i]['position']] = floatval($results[$i]['exec_memory']);
            }
        }
        $submit->status = $submit->calcStatus();

        $brain = new Brain();
        $brain->updateSubmit($submit);
        $brain->updatePending($submit);

        // If last update, move submission from Pending to Latest
        if ($submit->message != '') {
            $brain->erasePending($submit->id);
            $brain->addLatest($submit);
            $brain->trimLatest($submit->id);
        }
    }
}

?>
