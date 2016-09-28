<?php
require_once(__DIR__ . '/../config.php');

class DB {
    private $db;

    function __construct() {
        // Create connection
        $this->db = new mysqli($GLOBALS['DB_SERVER'], $GLOBALS['DB_USERNAME'],
                $GLOBALS['DB_PASSWORD'], $GLOBALS['DB_DATABASE'], $GLOBALS['DB_PORT']);

        // Check connection
        if ($this->db->connect_error) {
            die('Connection failed: ' . $this->db->connect_error);
        }

        // Set connection character encoding to UTF-8 (in case server is not configured properly)
        $this->db->set_charset('utf8');
    }

    function __destruct() {
        $this->db->close();
    }

    function tableExists($tableName) {
        return $this->db->query("SELECT 1 FROM `" . $tableName . "` LIMIT 1") == true;
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