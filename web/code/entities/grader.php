<?php
require_once(__DIR__ . '/../config.php');

class Grader {
    function call($path, $data) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $url = $GLOBALS['GRADER_URL'] . $path;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    function healthy() {
        $response = $this->call($GLOBALS['GRADER_PATH_HEALTHCHECK'], []);
        if ($response == null || $response['status'] != 200) {
            return false;
        }
        return true;
    }
}

?>
