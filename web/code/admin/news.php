<?php
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../page.php');
require_once(__DIR__ . '/../entities/news.php');

class AdminNewsPage extends Page {
    public function getTitle() {
        return 'O(N)::Admin';
    }

    public function getExtraScripts() {
        return array('/scripts/admin.js');
    }

    private function getNewsList() {
        $newsList = '';
        $brain = new Brain();
        foreach ($brain->getAllNews() as $entry) {
            $newsList .= '
                <div class="box boxlink" onclick="redirect(\'/admin/news/' . $entry['id'] . '\');">
                 <div class="news-content">
                    <div class="news-icon">
                        <i class="fa fa-' . $entry['icon'] . '" title="' . $entry['type'] . '"></i>
                    </div>
                    <div class="news-title">
                        ' . $entry['title'] . '
                    </div>
                    ' . $entry['content'] . '
                    <div class="separator" style="margin-top: 0.5rem;"></div>
                    <div class="news-date">Публикувано на ' . $entry['date'] . '</div>
                 </div>
                </div>
            ';
        }
        return $newsList;
    }

    private function getEditNewsForm($news) {
        // Header and Footer
        $headerText = $news->id == -1 ? 'Публикуване на новина' : 'Промяна на новина';
        $buttonText = $news->id == -1 ? 'Публикувай' : 'Запази';

        $content = '
            <h2>' . $headerText . '</h2>
            <div style="margin-bottom: 2px;">
                <input type="text" name="title" class="news-form-title" id="newsTitle" value="' . $news->title . '" title="Title">
            </div>
            <div class="right">
                <input type="text" name="date" class="news-form-date" id="newsDate" value="' . $news->date . '" title="Date">
            </div>
            <div class="centered" style="margin-bottom: 4px;">
                <input type="text" name="icon" id="newsIcon" value="' . $news->icon . '" style="width: 29%;" title="Icon">
                <input type="text" name="type" id="newsType" value="' . $news->type . '" style="width: 69%;" title="News Type">
            </div>
            <textarea name="content" class="news-form-content" id="newsContent" title="Content">' . $news->content . '</textarea>
            <div class="input-wrapper">
                <input type="submit" value="' . $buttonText . '" onclick="submitEditNewsForm();" class="button button-color-red">
            </div>
        ';
        return $content;
    }

    public function getContent() {
        $content = inBox('
            <h1>Админ::Новини</h1>

            <div class="centered">
                <input type="submit" value="Публикувай новина" onclick="redirect(\'news/new\');" class="button button-color-blue button-large">
            </div>
        ');
        $content .= $this->getNewsList();

        // Specific news entry is open
        if (isset($_GET['newsId'])) {
            $brain = new Brain();
            if ($_GET['newsId'] == 'new') {
                $news = new News();
            } else {
                $news = News::get($_GET['newsId']);
            }
            if ($news == null) {
                $content .= showMessage('ERROR', 'Не съществува новина с този идентификатор!');
            }

            $redirect = '/admin/news';
            $content .= '
                <script>
                    showEditNewsForm(`' . $this->getEditNewsForm($news) . '`, `' . $redirect . '`);
                </script>
            ';
        }

        return $content;
    }
}

?>