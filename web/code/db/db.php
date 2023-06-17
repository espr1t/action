<?php
require_once(__DIR__ . "/../config.php");

DB::init();

class DB {
    private static mysqli $db;

    public static function init(): void {
        // Create connection
        self::$db = new mysqli(
            $GLOBALS["DB_SERVER"],
            $GLOBALS["DB_USERNAME"],
            $GLOBALS["DB_PASSWORD"],
            $GLOBALS["DB_DATABASE"],
            $GLOBALS["DB_PORT"]
        );

        // Check connection
        if (self::$db->connect_error) {
            $error = self::$db->connect_error;
            error_log("ERROR: Could not connect to the DB: {$error}");
            die("Could not connect to the DB: {$error}");
        }

        // Set connection character encoding to UTF-8 (in case server is not configured properly)
        self::$db->set_charset("utf8");
    }

    function __destruct() {
        self::$db->close();
    }

    public static function tableExists(string $tableName): bool {
        return self::$db->query("SELECT 1 FROM `{$tableName}` LIMIT 1") == true;
    }

    public static function indexExists(string $indexName): bool {
        return self::$db->query("
            SELECT * FROM information_schema.statistics
            WHERE table_schema = '{$GLOBALS['DB_DATABASE']}' AND index_name = '$indexName'
            LIMIT 1
        ")->num_rows > 0;
    }

    public static function query(string $sqlQuery) {
        return self::$db->query($sqlQuery);
    }

    public static function lastId(): int {
        return self::$db->insert_id;
    }

    public static function escape(string $string): string {
        return self::$db->escape_string($string);
    }

}

?>