<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');

class News {
    public $id = -1;
    public $title = '';
    public $date = '';
    public $content = '';
    public $icon = '';
    public $type = '';

    public function __construct() {
        $this->title = 'Заглавие';
        $this->date = date('Y-m-d');
        $this->icon = 'arrow-circle-up';
        $this->type = 'Improvement';
    }

    private static function instanceFromArray($info) {
        $news = new News;
        $news->id = intval(getValue($info, 'id'));
        $news->title = getValue($info, 'title');
        $news->date = getValue($info, 'date');
        $news->content = getValue($info, 'content');
        $news->icon = getValue($info, 'icon');
        $news->type = getValue($info, 'type');

        return $news;
    }

    public static function get($id) {
        try {
            $info = Brain::getNews($id);
            if ($info == null) {
                return null;
            }
            return News::instanceFromArray($info);
        } catch (Exception $ex) {
            error_log('Could not get news with id ' . $id . '. Exception: ' . $ex->getMessage());
        }
        return null;
    }

    public function update() {
        return Brain::updateNews($this);
    }

    public function publish() {
        $result = Brain::addNews();
        if (!$result) {
            return false;
        }
        $this->id = $result;
        return $this->update();
    }

}

?>