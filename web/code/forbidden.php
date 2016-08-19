<?php
require_once('common.php');
require_once('page.php');

class ForbiddenPage extends Page {
    public function getTitle() {
        return 'O(N)::Forbidden';
    }
    
    public function getContent() {
        $content = inBox('Forbidden (403).');
        return $content;
    }
}

?>