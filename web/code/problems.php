<?php
require_once('common.php');
require_once('page.php');

class ProblemsPage extends Page {
    private $PROBLEMS_PATH = '../data/problems/';
    private $PROBLEM_INFO = '/problem_info.json';
    
    public function getTitle() {
        return 'O(N)::Problems';
    }
    
    private function getProblems() {
        $problems = '';
        $dirs = scandir($this->PROBLEMS_PATH);
        foreach ($dirs as $dir) {
            if ($dir == '.' || $dir == '..') {
                continue;
            }
            $json = file_get_contents($this->PROBLEMS_PATH . $dir . $this->PROBLEM_INFO);
            $info = json_decode($json);
            $name = $info->{'name'};
            $difficulty = $info->{'difficulty'};
            $solutions = count($info->{'accepted'});
            $source = $info->{'source'};
            
            $problems = $problems . '
            <div class="box boxlink">
                <div class="problemName">' . $name . '</div>
                <div class="problemInfo">
                    Difficulty: <strong>' . $difficulty . '</strong><br>
                    Solved by: <strong>' . $solutions . ' author(s)</strong><br>
                    Source: <strong>' . $source . '</strong>
                </div>
            </div>' . newLine();
        }
        return $problems;
    }
    
    public function getContent() {
        $userInfo = userInfo($this->user);
        $order_by_training = '<a href="?order=training">training</a>';
        $order_by_difficulty = '<a href="?order=difficulty">difficulty</a>';
        $order_by_solutions = '<a href="?order=solutions">solutions</a>';
        $text = '<h1>Problems</h1>
                 This is the list of all (official) problems.<br>
                 <div class="right">Order by: ' . $order_by_training . ' | ' . $order_by_difficulty . ' | ' . $order_by_solutions . '</div>';
        $content = inBox($text);
        $problems = $this->getProblems();
        return $userInfo . $content . $problems;
    }
    
}

?>