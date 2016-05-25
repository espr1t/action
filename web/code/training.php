<?php
require_once('common.php');
require_once('page.php');

class TrainingPage extends Page {
    public function getTitle() {
        return 'O(N)::Training';
    }
    
    public function getContent() {
        $userInfo = userInfo($this->user);
        $content = inBox('Under construction.');
        return $userInfo . $content;
    }
    
}

?>