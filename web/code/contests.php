<?php
require_once('common.php');
require_once('page.php');

class ContestsPage extends Page {
    public function getTitle() {
        return 'O(N)::Contests';
    }
    
    public function getContent() {
        $head = inBox('
            <h1>Състезания</h1>
            <div class="separator"></div>
            За момента няма публикувани състезания.
        ');
        $content = '';
        return $head . $content;
    }
    
}

?>