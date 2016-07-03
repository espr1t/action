<?php
require_once('common.php');
require_once('page.php');

class ErrorPage extends Page {
    public function getTitle() {
        return 'O(N)::Error';
    }
    
    public function getContent() {
        $content = inBox('Error Page (404).');
        return $content;
    }
    
}

?>