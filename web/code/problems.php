<?php
require_once('common.php');
require_once('page.php');

class ProblemsPage extends Page {
    private $PROBLEM_INFO = '/problem_info.json';
    private $PROBLEM_STATEMENT = '/problem_statement.html';
    
    public function getTitle() {
        return 'O(N)::Problems';
    }
    
    private function getAllProblems() {
        $problems = '';
        $dirs = scandir($GLOBALS['PATH_PROBLEMS']);
        foreach ($dirs as $dir) {
            if ($dir == '.' || $dir == '..') {
                continue;
            }
            $json = file_get_contents($GLOBALS['PATH_PROBLEMS'] . $dir . $this->PROBLEM_INFO);
            $info = json_decode($json);
            $id = $info->{'id'};
            $name = $info->{'name'};
            $difficulty = $info->{'difficulty'};
            $solutions = count($info->{'accepted'});
            $source = $info->{'source'};
            
            $problems = $problems . '
            <div class="box narrow boxlink">
                <a href="problems/' . $id . '" class="decorated">
                    <div class="problem-name">' . $name . '</div>
                    <div class="problem-info">
                        Difficulty: <strong>' . $difficulty . '</strong><br>
                        Solved by: <strong>' . $solutions . ' author(s)</strong><br>
                        Source: <strong>' . $source . '</strong>
                    </div>
                </a>
            </div>' . newLine();
        }
        return $problems;
    }

    private function getOrderings() {
        $order_by_training = '<a href="?order=training">training</a>';
        $order_by_difficulty = '<a href="?order=difficulty">difficulty</a>';
        $order_by_solutions = '<a href="?order=solutions">solutions</a>';
        return '<div class="smaller right">Order by: ' . $order_by_training . ' | ' . $order_by_difficulty . ' | ' . $order_by_solutions . '</div>';
    }

    private function getMainPage() {
        $text = '<h1>Problems</h1>
                 This is the list of all (official) problems.';
        $header = inBox($text);
        $orderings = $this->getOrderings();
        $problems = $this->getAllProblems();
        return $header . $orderings . $problems;
    }

    private function getProblem($id) {
        $problem = '';
        $dirs = scandir($GLOBALS['PATH_PROBLEMS']);
        foreach ($dirs as $dir) {
            if ($dir == '.' || $dir == '..') {
                continue;
            }
            $json = file_get_contents($GLOBALS['PATH_PROBLEMS'] . $dir . $this->PROBLEM_INFO);
            $info = json_decode($json);
            if ($info->{'id'} == $id) {
                $name = $info->{'name'};
                $source = $info->{'source'};
                $tl = $info->{'time_limit'};
                $ml = $info->{'memory_limit'};
                $statement = file_get_contents($GLOBALS['PATH_PROBLEMS'] . $dir . $this->PROBLEM_STATEMENT);

                $problem = '
                    <div class="box">
                        <div class="problem-title">' . $name . '</div>
                        <div class="problem-resources">Time Limit: ' . $tl . 's, Memory Limit: ' . $ml . 'MB</div>
                        <div class="problem-source">' . $source . '</div>
                        <div class="separator"></div>
                        <div class="problem-statement">' . $statement . '</div>
                    </div>' . newLine();
            }
        }
        if ($problem === '') {
            return $this.getMainPage();
        }
        return $problem;
    }

    public function getContent() {
        if (isset($_GET['problem'])) {
            return $this->getProblem($_GET['problem']);
        }
        return $this->getMainPage();
    }
}

?>