<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");

class Problem {
    private ?int $id = null;
    private ?string $name = null;
    private ?string $author = null;
    private ?string $folder = null;
    private ?float $timeLimit = null;
    private ?float $memoryLimit = null;
    private ?string $type = null;
    private ?string $difficulty = null;
    private ?string $logo = null;
    private ?string $description = null;
    private ?string $statement = null;
    private ?array $tags = null;
    private ?string $origin = null;
    private ?string $checker = null;
    private ?string $tester = null;
    private ?bool $floats = null;
    private ?int $waitPartial = null;
    private ?int $waitFull = null;
    private ?string $addedBy = null;
    private ?bool $visible = null;

    public function getId(): ?int {return $this->id;}
    public function getName(): ?string {return $this->name;}
    public function getAuthor(): ?string {return $this->author;}
    public function getFolder(): ?string {return $this->folder;}
    public function getTimeLimit(): ?float {return $this->timeLimit;}
    public function getMemoryLimit(): ?float {return $this->memoryLimit;}
    public function getType(): ?string {return $this->type;}
    public function getDifficulty(): ?string {return $this->difficulty;}
    public function getDescription(): ?string {return $this->description;}
    public function getLogo(): ?string {return $this->logo;}
    public function getStatement(): ?string {return $this->statement;}
    /** @return string[] */
    public function getTags(): ?array {return $this->tags;}
    public function getOrigin(): ?string {return $this->origin;}
    public function getChecker(): ?string {return $this->checker;}
    public function getTester(): ?string {return $this->tester;}
    public function getFloats(): ?bool {return $this->floats;}
    public function getWaitPartial(): ?int {return $this->waitPartial;}
    public function getWaitFull(): ?int {return $this->waitFull;}
    public function getAddedBy(): ?string {return $this->addedBy;}
    public function getVisible(): ?bool {return $this->visible;}

    function getPath(): string {
        return "{$GLOBALS['PATH_DATA']}/problems/{$this->getFolder()}";
    }

    function getStatementPath(): string {
        return "{$this->getPath()}/statement.html";
    }

    function getInfoPath(): string {
        return "{$this->getPath()}/info.json";
    }

    function getSolutionsPath(): string {
        return "{$this->getPath()}/Solutions";
    }

    function getTestsPath(): string {
        return "{$this->getPath()}/Tests";
    }

    function getCheckerPath(): string {
        return "{$this->getTestsPath()}/Checker/{$this->getChecker()}";
    }

    function getTesterPath(): string {
        return "{$this->getTestsPath()}/Tester/{$this->getTester()}";
    }

    function getUpdateEndpoint(): string {
        return "{$GLOBALS['WEB_ENDPOINT_ROOT']}/actions/update";
    }

    function getTestsEndpoint(): string {
        return "{$GLOBALS['WEB_ENDPOINT_ROOT']}/data/problems/{$this->getFolder()}/Tests";
    }

    function getCheckerEndpoint(): string {
        return "{$this->getTestsEndpoint()}/Checker/{$this->getChecker()}";
    }

    function getTesterEndpoint(): string {
        return "{$this->getTestsEndpoint()}/Tester/{$this->getTester()}";
    }

    public function setChecker(string $checker): void {$this->checker = $checker;}
    public function setTester(string $tester): void {$this->tester = $tester;}


    public function __construct() {
        $this->name = "ProblemName";
        $this->author = "Problem Author";
        $this->folder = "ProblemFolder";
        $this->timeLimit = 0.2;
        $this->memoryLimit = 64;
        $this->type = "ioi";
        $this->difficulty = "medium";
        $this->origin = "Problem Origin";
        $this->floats = false;
        $this->waitPartial = 0;
        $this->waitFull = 0;
        $this->addedBy = "unknown";
        $this->visible = false;

        # This is initially a template with the major sections (Statement/IO/Examples)
        $this->statement = file_get_contents(
            "{$GLOBALS['PATH_DATA']}/problems/statement.html"
        );
        $this->description = "";
    }

    private function toJson(): string {
        $jsonString = json_encode(array(
            "id" => $this->getId(),
            "name" => $this->getName(),
            "author" => $this->getAuthor(),
            "folder" => $this->getFolder(),
            "timeLimit" => $this->getTimeLimit(),
            "memoryLimit" => $this->getMemoryLimit(),
            "type" => $this->gettype(),
            "difficulty" => $this->getDifficulty(),
            "description" => $this->getDescription(),
            "logo" => $this->getLogo(),
            "tags" => $this->getTags(),
            "origin" => $this->getOrigin(),
            "checker" => $this->getChecker(),
            "tester" => $this->getTester(),
            "floats" => $this->getFloats(),
            "waitPartial" => $this->getWaitPartial(),
            "waitFull" => $this->getWaitFull(),
            "addedBy" => $this->getAddedBy(),
            "visible" => $this->getVisible()
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonString === false) {
            error_log("ERROR: Could not encode to JSON problem {$this->getId()}!");
            return "ERROR while encoding to JSON.";
        }
        return $jsonString;
    }

    public static function instanceFromArray(array $info, array $ignoredFields=array()): Problem {
        $problem = new Problem();
        if (!in_array("id", $ignoredFields))
            $problem->id = getIntValue($info, "id");
        if (!in_array("name", $ignoredFields))
            $problem->name = getStringValue($info, "name");
        if (!in_array("author", $ignoredFields))
            $problem->author = getStringValue($info, "author");
        if (!in_array("folder", $ignoredFields))
            $problem->folder = getStringValue($info, "folder");
        if (!in_array("timeLimit", $ignoredFields))
            $problem->timeLimit = getFloatValue($info, "timeLimit");
        if (!in_array("memoryLimit", $ignoredFields))
            $problem->memoryLimit = getFloatValue($info, "memoryLimit");
        if (!in_array("type", $ignoredFields))
            $problem->type = getStringValue($info, "type");
        if (!in_array("difficulty", $ignoredFields))
            $problem->difficulty = getStringValue($info, "difficulty");
        if (!in_array("description", $ignoredFields))
            $problem->description = getStringValue($info, "description");
        if (!in_array("logo", $ignoredFields))
            $problem->logo = getStringValue($info, "logo");
        if (!in_array("statement", $ignoredFields))
            $problem->statement = getStringValue($info, "statement");
        if (!in_array("tags", $ignoredFields))
            $problem->tags = getStringArray($info, "tags");
        if (!in_array("origin", $ignoredFields))
            $problem->origin = getStringValue($info, "origin");
        if (!in_array("checker", $ignoredFields))
            $problem->checker = getStringValue($info, "checker");
        if (!in_array("tester", $ignoredFields))
            $problem->tester = getStringValue($info, "tester");
        if (!in_array("floats", $ignoredFields))
            $problem->floats = getBoolValue($info, "floats");
        if (!in_array("waitPartial", $ignoredFields))
            $problem->waitPartial = getIntValue($info, "waitPartial");
        if (!in_array("waitFull", $ignoredFields))
            $problem->waitFull = getIntValue($info, "waitFull");
        if (!in_array("addedBy", $ignoredFields))
            $problem->addedBy = getStringValue($info, "addedBy");
        if (!in_array("visible", $ignoredFields))
            $problem->visible = getBoolValue($info, "visible");
        return $problem;
    }

    public static function get(int $id): ?Problem {
        try {
            $info = Brain::getProblem($id);
            return $info === null ? null : Problem::instanceFromArray($info);
        } catch (Exception $ex) {
            error_log("ERROR: Could not get problem {$id}! Exception: {$ex->getMessage()}");
        }
        return null;
    }

    /** @return Problem[] */
    private static function getProblems(array $problemsAsArrays): array {
        return array_map(
            function ($entry) {
                return Problem::instanceFromArray($entry);
            }, $problemsAsArrays
        );
    }

    /** @return Problem[] */
    public static function getAllTasks(): array {
        return self::getProblems(Brain::getAllTasks());
    }

    /** @return Problem[] */
    public static function getAllGames(): array {
        return self::getProblems(Brain::getAllGames());
    }

    public static function getGameByName(string $name): ?Problem {
        foreach (self::getAllGames() as $game) {
            if (getGameUrlName($game->getName()) == $name)
                return $game;
        }
        return null;
    }

    public function update(): bool {
        // Update the problem meta information (JSON file in the problem folder)
        if (!file_put_contents($this->getInfoPath(), $this->toJson())) {
            error_log("ERROR: Unable to record problem configuration to file: '{$this->getInfoPath()}'!");
            return false;
        }

        // Update the problem statement (HTML file in the problem folder)
        if (!file_put_contents($this->getStatementPath(), $this->getStatement())) {
            error_log("ERROR: Unable to write statement to file: '{$this->getStatementPath()}'!");
            return false;
        }
        return Brain::updateProblem($this);
    }

    public function updateChecker(): bool {
        return Brain::updateChecker($this);
    }

    public function updateTester(): bool {
        return Brain::updateTester($this);
    }

    public function create(): bool {
        // Add the problem to the database
        $result = Brain::addProblem();
        if (!$result) {
            return false;
        }
        $this->id = $result;

        // Create main folder
        if (!mkdir($this->getPath())) {
            error_log("ERROR: Unable to create problem folder: '{$this->getPath()}'!");
            return false;
        }

        // Create tests folder
        if (!mkdir($this->getTestsPath())) {
            error_log("ERROR: Unable to create problem tests folder: '{$this->getTestsPath()}'!");
            return false;
        }

        // Create solutions folder
        if (!mkdir($this->getSolutionsPath())) {
            error_log("ERROR: Unable to create problem solutions folder: '{$this->getSolutionsPath()}'!");
            return false;
        }

        // Update the meta information and the statement
        return $this->update();
    }

    function validate(): string {
        if (!preg_match('/(*UTF8)^([0-9A-Za-zА-Яа-я.,!*\/ -]){1,32}$/', $this->getName()))
            return "Въведеното име на задача е невалидно!";

        if (!preg_match('/(*UTF8)^([A-Za-zА-Яа-я -]){1,32}$/', $this->getAuthor()))
            return "Въведеното име на автор е невалидно!";

        if (!preg_match('/([0-9A-Za-z_-]){1,32}$/', $this->getFolder()))
            return "Въведената папка е невалидна!";

        if (!preg_match('/(*UTF8)^([0-9A-Za-zА-Яа-я.,:! -]){1,128}$/', $this->getOrigin()))
            return "Въведеният източник е невалиден!";

        if (!$this->getTimeLimit())
            return "Въведеното ограничение по време е невалидно!";

        if (!$this->getMemoryLimit())
            return "Въведеното ограничение по памет е невалидно!";

        if (!array_key_exists($this->getType(), $GLOBALS["PROBLEM_TYPES"]))
            return "Въведеният тип е невалиден!";

        if (!array_key_exists($this->getDifficulty(), $GLOBALS["PROBLEM_DIFFICULTIES"]))
            return "Въведената сложност {$this->getDifficulty()} е невалидна!";

        foreach ($this->getTags() as $tag) {
            if (!array_key_exists($tag, $GLOBALS["PROBLEM_TAGS"])) {
                return "Въведеният таг {$tag} е невалиден!";
            }
        }
        return "";
    }
}

?>