<?php
require_once(__DIR__ . '/../config.php');

class DB {
    private mysqli $db;

    function __construct() {
        // Create connection
        $this->db = new mysqli(
            $GLOBALS['DB_SERVER'],
            $GLOBALS['DB_USERNAME'],
            $GLOBALS['DB_PASSWORD'],
            $GLOBALS['DB_DATABASE'],
            $GLOBALS['DB_PORT']
        );

        // Check connection
        if ($this->db->connect_error) {
            die("Connection failed: {$this->db->connect_error}.");
        }

        // Set connection character encoding to UTF-8 (in case server is not configured properly)
        $this->db->set_charset("utf8");
    }

    function __destruct() {
        $this->db->close();
    }

    function tableExists(string $tableName): bool {
        return $this->db->query("SELECT 1 FROM `{$tableName}` LIMIT 1") == true;
    }

    function indexExists(string $indexName): bool {
        return $this->db->query("
            SELECT * FROM information_schema.statistics
            WHERE table_schema = '{$GLOBALS['DB_DATABASE']}' AND index_name = '$indexName'
            LIMIT 1
        ")->num_rows > 0;
    }

    function query(string $sqlQuery) {
        return $this->db->query($sqlQuery);
    }

    function lastId(): int {
        return $this->db->insert_id;
    }

    function escape(string $string): string {
        return $this->db->escape_string($string);
    }

}

?>