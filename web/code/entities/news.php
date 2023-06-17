<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");

class News {
    private ?int $id = null;
    private ?string $title = null;
    private ?string $date = null;
    private ?string $content = null;
    private ?string $icon = null;
    private ?string $type = null;

    public function getId(): int {return $this->id;}
    public function getTitle(): string {return $this->title;}
    public function getDate(): string {return $this->date;}
    public function getContent(): string {return $this->content;}
    public function getIcon(): string {return $this->icon;}
    public function getType(): string {return $this->type;}

    public function setId(int $id): void {$this->id = $id;}
    public function setTitle(string $title): void {$this->title = $title;}
    public function setDate(string $date): void {$this->date = $date;}
    public function setContent(string $content): void {$this->content = $content;}
    public function setIcon(string $icon): void {$this->icon = $icon;}
    public function setType(string $type): void {$this->type = $type;}


    public function __construct() {
        $this->id = -1;
        $this->title = "Заглавие";
        $this->date = date("Y-m-d");
        $this->content = "";
        $this->icon = "arrow-circle-up";
        $this->type = "Improvement";
    }

    private static function instanceFromArray($info): News {
        $news = new News;
        $news->id = getIntValue($info, "id");
        $news->title = getStringValue($info, "title");
        $news->date = getStringValue($info, "date");
        $news->content = getStringValue($info, "content");
        $news->icon = getStringValue($info, "icon");
        $news->type = getStringValue($info, "type");

        return $news;
    }

    public static function get(int $id): ?News {
        try {
            $info = Brain::getNews($id);
            return !$info ? null : News::instanceFromArray($info);
        } catch (Exception $ex) {
            error_log("Could not get news with id {$id}. Exception: '{$ex->getMessage()}'");
        }
        return null;
    }

    /** @return News[] */
    public static function getAll(): array {
        return array_map(
            function ($entry) {
                return News::instanceFromArray($entry);
            }, Brain::getAllNews()
        );
    }

    public function update(): bool {
        return Brain::updateNews($this);
    }

    public function publish(): bool {
        $result = Brain::addNews();
        if (!$result) {
            return false;
        }
        $this->id = $result;
        return $this->update();
    }

}

?>