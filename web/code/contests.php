<?php
require_once('common.php');
require_once('page.php');

class ContestsPage extends Page {
    public function getTitle() {
        return 'O(N)::Contests';
    }
    
    public function getContent() {
        $userInfo = userInfo($this->user);
        $content = inBox('Under construction.');
        return $userInfo . $content;
    }
    
}

?>