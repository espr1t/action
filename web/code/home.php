<?php
require_once("db/brain.php");
require_once("common.php");
require_once("page.php");

class HomePage extends Page {
    public function getTitle(): string {
        return "O(N)::Home";
    }

    public function getContent(): string {
        $news = inBox("
            <h1>Новини</h1>
            Тук ще намерите новини, свързани със системата и състезанията на нея.
        ");

        foreach (Brain::getAllNews() as $entry) {
            $news .= inBox("
                 <div class='news-content'>
                    <div class='news-icon'>
                        <i class='fa fa-{$entry['icon']}' title='{$entry['type']}'></i>
                    </div>
                    <div class='news-title'>{$entry['title']}</div>
                    {$entry['content']}
                    <div class='separator' style='margin-top: 0.5rem;'></div>
                    <div class='news-date'>Публикувано на {$entry['date']}</div>
                 </div>
            ");
        }

        return $news;
    }
}

?>