<?php
require_once('common.php');
require_once('page.php');

class TrainingPage extends Page {
    public function getTitle() {
        return 'O(N)::Training';
    }
    
    public function getContent() {
        $content = inBox('Under construction.');
        return $content;
    }
    
}

?>