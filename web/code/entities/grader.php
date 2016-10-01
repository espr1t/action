<?php
require_once(__DIR__ . '/../config.php');

class Grader {
    function call($path, $data) {
        $curl = curl_init();

        // Setup the connection
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $GLOBALS['GRADER_URL'] . $path);

        // Setup authentication
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $GLOBALS['GRADER_USERNAME'] . ':' . $GLOBALS['GRADER_PASSWORD']);

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

    function healthy() {
        $response = $this->call($GLOBALS['GRADER_PATH_HEALTHCHECK'], []);
        return $response['status'] == 200;
    }
}

?>
