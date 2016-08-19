<?php
require_once('db/db.php');

class Brain {
    private $db;

    function __construct() {
        $this->db = new DB();
    }

    function getResults($sqlResults) {
        while ($row = $sqlResults->fetch_assoc()) {
            $rows[] = $row;
        }
        $sqlResults->close();
        return $rows;
    }

    function publishNews($date, $title, $content) {
        $result = $this->db->query("
            INSERT INTO `News`(`date`, `title`, `content`)
            VALUES (
                '" . $this->db->escape($date) . "',
                '" . $this->db->escape($title) . "',
                '" . $this->db->escape($content) . "'
            );
        ");

        if (!$result) {
            error_log('Could not publish news with title "' . $title . '"!');
            return false;
        }
        return true;
    }

    function getNews() {
        $result = $this->db->query("
            SELECT * FROM `News` ORDER BY date DESC LIMIT 20;
        ");
        if (!$result) {
            error_log('Could not execute getNews() query properly!');
            return false;
        }
        return $this->getResults($result);
    }

}

?>