<?php
require_once('config.php');
require_once('widgets.php');

class News {
    public $id = -1;
    public $title = '';
    public $date = '';
    public $content = '';
    
    public function __construct() {
        $this->title = 'Заглавие';
        $this->date = date('Y-m-d');
    }

    private static function instanceFromArray($info) {
        $news = new News;
        $news->id = intval(getValue($info, 'id'));
        $news->title = getValue($info, 'title');
        $news->date = getValue($info, 'date');
        $news->content = getValue($info, 'content');
        return $news;
    }

    public static function get($id) {
        $brain = new Brain();
        try {
            $info = $brain->getNews($id);
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
        $brain = new Brain();
        return $brain->updateNews($this);
    }

    public function create() {
        $brain = new Brain();
        $result = $brain->addNews();
        if (!$result) {
            return false;
        }
        $this->id = $result;
        return $this->update();
    }

}

?>