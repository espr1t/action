<?php
require_once('config.php');

class DB {
    private $db;

    function __construct() {
        // Create connection
        $this->db = new mysqli($GLOBALS['DB_SERVER'], $GLOBALS['DB_USERNAME'], $GLOBALS['DB_PASSWORD'],
                               $GLOBALS['DB_DATABASE'], $GLOBALS['DB_PORT']);

        // Check connection
        if ($this->db->connect_error) {
            die('Connection failed: ' . $this->db->connect_error);
        } 
        // echo 'Connected to database ' . $GLOBALS['DB_DATABASE'] . '.<br>';
    }

    function __destruct() {
        $this->db->close();
    }

    function checkTable($tableName) {
        return $this->db->query("SELECT 1 FROM `" . $tableName . "` LIMIT 1;") == true;
    }

    function query($sqlQuery) {
        return $this->db->query($sqlQuery);
    }

    function lastId() {
        return $this->db->insert_id;
    }

    function escape($string) {
        return $this->db->escape_string($string);
    }
}

?>