<?php
require_once('common.php');
require_once('page.php');

class HelpPage extends Page {
    public function getTitle() {
        return 'O(N)::Help';
    }
    
    public function getContent() {
        $content = inBox('
            <h1>Инструкции</h1>
            Тук е описано как работи системата и какво да очаквате, когато направите някое действие.
        ') . inBox('
            <h2>Предаване на решение</h2>
        ') . inBox('
            <h2>Статус кодове</h2>
        ') . inBox('
            <h2>Тренировка</h2>
        ') . inBox('
            <h2>Постижения</h2>
        ') . inBox('
            <h2>Ранклиста и точки</h2>
        ');

        return $content;
    }
    
}

?>