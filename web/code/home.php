<?php
require_once('common.php');
require_once('page.php');

class HomePage extends Page {
    public function getTitle() {
        return 'O(N)::Home';
    }
    
    public function getContent() {
        $news = inBox('
            <h1>Новини</h1>
            Тук ще бъдат публикувани новини, свързани със системата и състезанията на нея.
        ');

        $fileNames = scandir($GLOBALS['PATH_NEWS']);
        rsort($fileNames);
        foreach ($fileNames as $fileName) {
            if ($fileName == '.' || $fileName == '..') {
                continue;
            }
            $file = fopen($GLOBALS['PATH_NEWS'] . $fileName, 'r');
            $title = fgets($file);
            $date = fgets($file);
            $body = '';
            while (($line = fgets($file)) !== false) {
                $body .= $line;
            }
            fclose($file);

            $content = '';
            $content .= '<div class="news-title">' . $title . '</div>';
            $content .= $body;
            $content .= '<div class="separator"></div>';
            $content .= '<div class="news-date">Публикувано на ' . $date . '</div>';

            $news .= inBox($content);
        }
        return $news;
    }
}

?>