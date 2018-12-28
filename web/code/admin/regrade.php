<?php
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/grader.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../page.php');
require_once(__DIR__ . '/../queue.php');
require_once(__DIR__ . '/../entities/submit.php');

class AdminRegradePage extends Page {
    public function getTitle() {
        return 'O(N)::Admin - Regrade';
    }

    public function regradeSubmit($submitId) {
        $submit = Submit::get($submitId);
        $submit->reset();
        $submit->send();
    }

    private function regradeStuck() {
        $stuckStatus = array(
            $GLOBALS['STATUS_WAITING'],
            $GLOBALS['STATUS_PREPARING'],
            $GLOBALS['STATUS_COMPILING'],
            $GLOBALS['STATUS_TESTING']
        );

        $brain = new Brain();
        $stuckSubmits = array();
        foreach ($stuckStatus as $status) {
            $stuckOfType = $brain->getAllSubmits($status);
            if ($stuckOfType != null) {
                $stuckSubmits = array_merge($stuckSubmits, $stuckOfType);
            }
        }

        foreach ($stuckSubmits as $submit) {
            $this->regradeSubmit($submit['id']);
        }
    }

    public function getContent() {
        if (isset($_GET['submitId'])) {
            if ($_GET['submitId'] == 'stuck' || $_GET['submitId'] == 'pending') {
                $this->regradeStuck();
            } else {
                $this->regradeSubmit($_GET['submitId']);
            }
            redirect('/admin/regrade');
        }
        $page = new QueuePage($this->user);
        return $page->getContent();
    }

}

?>
