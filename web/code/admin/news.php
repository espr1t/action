<?php
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../page.php");
require_once(__DIR__ . "/../entities/news.php");

class AdminNewsPage extends Page {
    public function getTitle(): string {
        return "O(N)::Admin - News";
    }

    public function getExtraScripts(): array {
        return array("/scripts/admin.js");
    }

    private function getNewsList(): string {
        $newsList = "";
        foreach (News::getAll() as $news) {
            $newsList .= "
                <div class='box boxlink' onclick='redirect(`/admin/news/{$news->getId()}`);'>
                 <div class='news-content'>
                    <div class='news-icon'>
                        <span class='tooltip--left' data-tooltip='{$news->getType()}'>
                            <i class='fa fa-{$news->getIcon()}'></i>
                        </span>
                    </div>
                    <div class='news-title'>
                        {$news->getTitle()}
                    </div>
                    {$news->getContent()}
                    <div class='separator' style='margin-top: 0.5rem;'></div>
                    <div class='news-date'>Публикувано на {$news->getDate()}</div>
                 </div>
                </div>
            ";
        }
        return $newsList;
    }

    private function getEditNewsForm(News $news): string {
        // Header and Footer
        $headerText = $news->getId() == -1 ? "Публикуване на новина" : "Промяна на новина";
        $buttonText = $news->getId() == -1 ? "Публикувай" : "Запази";

        return "
            <h2>{$headerText}</h2>
            <div style='margin-bottom: 2px;'>
                <input type='text' name='title' class='news-form-title' id='newsTitle' value='{$news->getTitle()}' title='Title'>
            </div>
            <div class='right'>
                <input type='text' name='date' class='news-form-date' id='newsDate' value='{$news->getDate()}' title='Date'>
            </div>
            <div class='centered' style='margin-bottom: 4px;'>
                <input type='text' name='icon' id='newsIcon' value='{$news->getIcon()}' style='width: 29%;' title='Icon'>
                <input type='text' name='type' id='newsType' value='{$news->getType()}' style='width: 69%;' title='News Type'>
            </div>
            <textarea name='content' class='news-form-content' id='newsContent' title='Content'>{$news->getContent()}</textarea>
            <div class='input-wrapper'>
                <input type='submit' value='{$buttonText}' onclick='submitEditNewsForm();' class='button button-color-red'>
            </div>
        ";
    }

    public function getContent(): string {
        $content = inBox("
            <h1>Админ::Новини</h1>

            <div class='centered'>
                <input type='submit' value='Публикувай новина' onclick='redirect(`news/new`);' class='button button-color-blue button-large'>
            </div>
        ");
        $content .= $this->getNewsList();

        // Specific news entry is open
        if (isset($_GET["newsId"])) {
            if ($_GET["newsId"] == "new") {
                $news = new News();
            } else {
                $news = News::get($_GET["newsId"]);
            }
            if ($news == null) {
                $content .= showNotification("ERROR", "Не съществува новина с този идентификатор!");
            }

            $redirect = "/admin/news";
            $content .= "
                <script>
                    showEditNewsForm(`{$this->getEditNewsForm($news)}`, `{$redirect}`);
                </script>
            ";
        }

        return $content;
    }
}

?>