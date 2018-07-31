<?php
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/grader.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../page.php');
require_once(__DIR__ . '/../queue.php');
require_once(__DIR__ . '/../entities/submit.php');
require_once(__DIR__ . '/regrade.php');

class AdminRetestPage extends Page {
    public function getTitle() {
        return 'O(N)::Admin - Retest';
    }

    private function retestLatest($problemId) {
        $brain = new Brain();
        $submits = $brain->getProblemSubmits($problemId);
        $seen = array();
        for ($i = count($submits) - 1; $i >= 0; $i -= 1) {
            if (!in_array($submits[$i]['userId'], $seen)) {
                array_push($seen, $submits[$i]['userId']);
                AdminRegradePage::regradeSubmit($submits[$i]['id']);
            }
        }
    }

    private function retestProblem($problemId) {
        $brain = new Brain();
        $submits = $brain->getProblemSubmits($problemId);
        foreach ($submits as $submit) {
            AdminRegradePage::regradeSubmit($submit['id']);
        }
    }

    public function getContent() {
        if (isset($_GET['problemId'])) {
            if (isset($_GET['latest'])) {
                $this->retestLatest($_GET['problemId']);
                redirect('/admin/retest/latest');
            } else {
                $this->retestProblem($_GET['problemId']);
                redirect('/admin/retest');
            }
        }
        $page = new QueuePage($this->user);
        return $page->getContent();
    }

}

?>
