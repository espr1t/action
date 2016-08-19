<?php
require_once('common.php');
require_once('page.php');

class TrainingPage extends Page {
    public function getTitle() {
        return 'O(N)::Training';
    }
    
    public function getContent() {
        $head = inBox('
            <h1>Подготовка</h1>
            Тук ще има подготовка, включваща задачи, групирани по теми, в нарастваща сложност.
        ');
        $content = '';
        return $head . $content;
    }
    
}

?>