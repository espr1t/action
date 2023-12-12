<?php
require_once("actions/print_pdf.php");
require_once("db/brain.php");
require_once("entities/problem.php");
require_once("entities/submit.php");
require_once("config.php");
require_once("common.php");
require_once("page.php");
require_once("events.php");


class ProblemsPage extends Page {
    private ?string $problemName = null;

    public function getTitle(): string {
        if ($this->problemName == null) {
            return "O(N)::Problems";
        }
        return "O(N)::{$this->problemName}";
    }

    public function getExtraScripts(): array {
        return array("/scripts/language_detector.js");
    }

    public function getExtraStyles(): array {
        return array("/styles/tooltips.css");
    }

    public function onLoad(): string {
        return "addPreTags()";
    }

    public function getProblemBox(Problem $taskInfo, bool $haveTried, bool $haveSolved): string {
        $statusTooltip = "Още не сте пробвали да решите тази задача.";
        $statusIconClass = "fal fa-circle gray";
        if ($haveTried) {
            $statusTooltip = "Пробвали сте неуспешно тази задача.";
            $statusIconClass = "fas fa-times-circle red";
        }
        if ($haveSolved) {
            $statusTooltip = "Вече сте решили успешно тази задача.";
            $statusIconClass = "fas fa-check-circle green";
        }
        $status = "
            <div class='tooltip--right' data-tooltip='{$statusTooltip}'>
                <i class='{$statusIconClass}'></i>
            </div>
        ";

        $difficultyTooltip = "Unknown";
        $difficultyIconClass = "fas fa-question";
        switch ($taskInfo->getDifficulty()) {
            case $GLOBALS["PROBLEM_DIFFICULTY_TRIVIAL"]:
                $difficultyTooltip = "Много лесна";
                $difficultyIconClass = "fad fa-duck";
                break;
            case $GLOBALS["PROBLEM_DIFFICULTY_EASY"]:
                $difficultyTooltip = "Лесна";
                $difficultyIconClass = "fas fa-feather-alt";
                break;
            case $GLOBALS["PROBLEM_DIFFICULTY_MEDIUM"]:
                $difficultyTooltip = "Средна";
                $difficultyIconClass = "fas fa-brain";
                break;
            case $GLOBALS["PROBLEM_DIFFICULTY_HARD"]:
                $difficultyTooltip = "Трудна";
                $difficultyIconClass = "fas fa-paw-claws";
                break;
            case $GLOBALS["PROBLEM_DIFFICULTY_BRUTAL"]:
                $difficultyTooltip = "Много трудна";
                $difficultyIconClass = "fas fa-biohazard";
                break;
        }
        $difficulty = "
            <div class='tooltip--top' data-tooltip='{$difficultyTooltip}'>
                <i class='{$difficultyIconClass}'></i>
            </div>
        ";

        $solutionsTooltip = "Решена от {$taskInfo->numSolutions} човек" . ($taskInfo->numSolutions != 1 ? "a" : "");
        $solutions = "
            <div class='tooltip--top' data-tooltip='{$solutionsTooltip}'>
                <span style='font-weight: bold;'>{$taskInfo->numSolutions} <i class='fas fa-users fa-sm'></i></span>
            </div>
        ";

        $successTooltip = "{$taskInfo->successRate}% успеваемост";
        $success = "
            <div class='tooltip--top' data-tooltip='{$successTooltip}'>
                <span style='font-weight: bold;'>{$taskInfo->successRate} <i class='fas fa-percentage fa-sm'></i></span>
            </div>
        ";

        $box = "
            <a href='/problems/{$taskInfo->getId()}' class='decorated'>
                <div class='box narrow boxlink'>
                    <div class='problem-status'>{$status}</div>
                    <div class='problem-name'>{$taskInfo->getName()}</div>
                    <div class='problem-stats'>{$difficulty} | {$solutions} | {$success}</div>
                </div>
            </a>
        ";
        // Make hidden problems grayed out (actually visible only to admins).
        if (!$taskInfo->getVisible()) {
            $box = "<div style='opacity: 0.5;'>{$box}</div>";
        }
        return $box;
    }

    // TODO: Make this function faster by pre-calculating problem stats
    public function getTasks(?array $tasksList=null): string {
        /** @var Problem[] $tasksInfo */
        $tasksInfo = array();
        foreach (Problem::getAllTasks() as $task) {
            if ($tasksList == null || in_array($task->getId(), $tasksList)) {
                array_push($tasksInfo, $task);
            }
        }

        $totalSubmits = array();
        $successfulSubmits = array();
        foreach ($tasksInfo as $task) {
            $totalSubmits[$task->getId()] = 0;
            $successfulSubmits[$task->getId()] = 0;
        }

        foreach (Brain::getProblemStatusCounts() as $statusCnt) {
            $problemId = $statusCnt["problemId"];
            if (array_key_exists($problemId, $totalSubmits)) {
                $totalSubmits[$problemId] += $statusCnt["count"];
                if ($statusCnt["status"] == $GLOBALS["STATUS_ACCEPTED"])
                    $successfulSubmits[$problemId] += $statusCnt["count"];
            }
        }

        // Calculate number of solutions and success rate
        for ($i = 0; $i < count($tasksInfo); $i++) {
            // `numSolutions` and `successRate` are not parts of Problem originally
            // Add them here (kinda ugly) so we can use them in getProblemBox().
            $tasksInfo[$i]->numSolutions = $successfulSubmits[$tasksInfo[$i]->getId()];
            $tasksInfo[$i]->successRate = $totalSubmits[$tasksInfo[$i]->getId()] == 0 ? 0.0 :
                round(100.0 * $successfulSubmits[$tasksInfo[$i]->getId()] / $totalSubmits[$tasksInfo[$i]->getId()]);
        }

        $problemTried = array();
        $problemSolved = array();
        if ($this->user->getId() != -1) {
            foreach (Submit::getUserSubmits($this->user->getId()) as $submit) {
                $problemTried[$submit->getProblemId()] = true;
                if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"])
                    $problemSolved[$submit->getProblemId()] = true;
            }
        }

        for ($i = 0; $i < count($tasksInfo); $i++) {
            $tasksInfo[$i]->box = $this->getProblemBox($tasksInfo[$i],
                array_key_exists($tasksInfo[$i]->getId(), $problemTried),
                array_key_exists($tasksInfo[$i]->getId(), $problemSolved)
            );
        }

        // Order by solutions or difficulty, if requested
        if (isset($_GET["order"])) {
            switch ($_GET["order"]) {
                case "difficulty":
                    $orderPriority = ["difficulty", "solutions", "success"];
                    break;
                case "success":
                    $orderPriority = ["success", "solutions", "difficulty"];
                    break;
                default:
                    $orderPriority = ["solutions", "difficulty", "success"];
            }
            $numericDifficulty = array(
                $GLOBALS["PROBLEM_DIFFICULTY_TRIVIAL"] => 0,
                $GLOBALS["PROBLEM_DIFFICULTY_EASY"] => 1,
                $GLOBALS["PROBLEM_DIFFICULTY_MEDIUM"] => 2,
                $GLOBALS["PROBLEM_DIFFICULTY_HARD"] => 3,
                $GLOBALS["PROBLEM_DIFFICULTY_BRUTAL"] => 4
            );
            usort($tasksInfo, function(Problem $left, Problem $right) use($numericDifficulty, $orderPriority) {
                foreach ($orderPriority as $priority) {
                    $lValue = 0;
                    $rValue = 0;
                    switch ($priority) {
                        case "solutions":
                            $lValue = $left->numSolutions;
                            $rValue = $right->numSolutions;
                            break;
                        case "difficulty":
                            $lValue = -$numericDifficulty[$left->getDifficulty()];
                            $rValue = -$numericDifficulty[$right->getDifficulty()];
                            break;
                        case "success":
                            $lValue = $left->successRate;
                            $rValue = $right->successRate;
                            break;
                    }
                    if ($lValue != $rValue)
                        return $rValue - $lValue;
                }
                // If everything else is the same, order by ID
                return $left->getId() - $right->getId();
            });
        }

        $tasks = "";
        foreach ($tasksInfo as $task) {
            // Don't show hidden tasks
            if (!canSeeProblem($this->user, $task->getVisible()))
                continue;
            $tasks .= $task->box;
        }
        return $tasks;
    }

    private function getOrderings(): string {
        $orderByDifficulty = "<a href='?order=difficulty'>сложност</a>";
        $orderBySolutions = "<a href='?order=solutions'>брой решения</a>";
        $orderBySuccessRate = "<a href='?order=success'>успеваемост</a>";
        return "
            <div class='right' style='font-size: smaller;'>
                Подреди по: {$orderByDifficulty} | {$orderBySolutions} | {$orderBySuccessRate}
            </div>
        ";
    }

    private function getMainPage(): string {
        $text = "<h1>Задачи</h1>
                 Тук можете да намерите списък с всички задачи на системата.
        ";
        return inBox($text) . $this->getOrderings() . $this->getTasks();
    }

    private function getStatsBox(Problem $problem): string {
        $bestTime = 1e10;
        $bestMemory = 1e10;
        $numTotalSubmits = 0;
        $numAcceptedSubmits = 0;
        foreach (Submit::getProblemSubmits($problem->getId()) as $submit) {
            $numTotalSubmits++;
            if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"]) {
                $numAcceptedSubmits++;
                $bestTime = min([$bestTime, max($submit->getExecTime())]);
                $bestMemory = min([$bestMemory, max($submit->getExecMemory())]);
            }
        }
        $successRate = $numTotalSubmits == 0 ? 0.0 : 100.0 * $numAcceptedSubmits / $numTotalSubmits;

        $content = "
            <h2><span class='blue'>{$problem->getName()}</span> :: Статистики</h2>
            <div class='centered'>
                <div class='problem-stats-field'>
                    <div class='problem-stats-field-circle'>" . ($numAcceptedSubmits == 0 ? '-' : sprintf("%.2fs", $bestTime)) . "</div>
                    <div class='problem-stats-field-label'>време</div>
                </div>
                <div class='problem-stats-field'>
                    <div class='problem-stats-field-circle'>" . ($numAcceptedSubmits == 0 ? '-' : sprintf("%.2fMB", $bestMemory)) . "</div>
                    <div class='problem-stats-field-label'>памет</div>
                </div>
                <div class='problem-stats-field'>
                    <div class='problem-stats-field-circle'>" . ucfirst($problem->getDifficulty()) . "</div>
                    <div class='problem-stats-field-label'>сложност</div>
                </div>
                <div class='problem-stats-field'>
                    <div class='problem-stats-field-circle'>{$numAcceptedSubmits}</div>
                    <div class='problem-stats-field-label'>решения</div>
                </div>
                <div class='problem-stats-field'>
                    <div class='problem-stats-field-circle'>" . sprintf("%.2f%%", $successRate) . "</div>
                    <div class='problem-stats-field-label'>успеваемост</div>
                </div>
            </div>
        ";

        $redirect = "/problems/{$problem->getId()}";
        return "
            <script>
                showActionForm(`{$content}`, `{$redirect}`);
            </script>
        ";
    }

    private function getUsersBox(Problem $problem): string {
        /** @var Submit[] $usersBest */
        $usersBest = array();
        foreach (Submit::getAllSubmits(-1, $problem->getId(), "AC") as $submit) {
            // Skip system user
            if ($submit->getUserId() < 1)
                continue;

            if (!array_key_exists($submit->getUserId(), $usersBest)) {
                // If first AC for this user on this problem add it to the list
                $usersBest[$submit->getUserId()] = $submit;
            } else {
                // Otherwise, see if this submission is better than the current best
                if (max($submit->getExecTime()) < max($usersBest[$submit->getUserId()]->getExecTime()) ||
                    (max($submit->getExecTime()) == max($usersBest[$submit->getUserId()]->getExecTime()) &&
                        max($submit->getExecMemory()) < max($usersBest[$submit->getUserId()]->getExecMemory()))) {
                    $usersBest[$submit->getUserId()] = $submit;
                }
            }
        }

        $tableContent = "";
        foreach ($usersBest as $submit) {
            $userLink = getUserLink($submit->getUserName());
            $maxExecTime = sprintf("%.2f", max($submit->getExecTime()));
            $maxExecMemory = sprintf("%.2f", max($submit->getExecMemory()));
            $tableContent .= "
                <tr>
                    <td>{$userLink}</td>
                    <td>{$submit->getLanguage()}</td>
                    <td>{$maxExecTime}s</td>
                    <td>{$maxExecMemory}MB</td>
                    <td>{$submit->getSubmitted()}</td>
                </tr>
            ";
        }

        $content = "
            <h2><span class='blue'>{$problem->getName()}</span> :: Решения</h2>
            <div class='centered'>
                <table class='default'>
                    <thead>
                        <tr>
                            <th>Потребител</th>
                            <th>Език</th>
                            <th>Време</th>
                            <th>Памет</th>
                            <th>Дата</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$tableContent}
                    </tbody>
                </table>
            </div>
        ";

        $redirect = "/problems/{$problem->getId()}";
        return "
            <script>
                showActionForm(`{$content}`, `{$redirect}`);
            </script>
        ";
    }

    private function getTagsBox(Problem $problem): string {
        $tags = "";
        foreach ($problem->getTags() as $tag) {
            $tags .= ($tags == "" ? "" : " | ") . $GLOBALS["PROBLEM_TAGS"][$tag];
        }
        $content = "
            <h2><span class='blue'>{$problem->getName()}</span> :: Тагове</h2>
            <div class='centered'>{$tags}</div>
        ";

        $redirect = "/problems/{$problem->getId()}";
        return "
            <script>
                showActionForm(`{$content}`, `{$redirect}`);
            </script>
        ";
    }

    private function getStatement(Problem $problem): string {
        $statement = file_get_contents($problem->getStatementPath());

        $submitFormContent = "
            <h2><span class='blue'>{$problem->getName()}</span> :: Предаване на Решение</h2>
            <div class='center'>
                <textarea name='source' class='submit-source' cols=80 rows=24 maxlength=50000 id='source'></textarea>
            </div>
            <div class='italic right' style='font-size: 0.8em;'>Detected language: <span id='language'>?</span></div>
            <div class='center'><input type='submit' value='Изпрати' onclick='submitSubmitForm({$problem->getId()})' class='button button-color-red'></div>
        ";

        if ($this->user->getAccess() >= $GLOBALS["ACCESS_SUBMIT_SOLUTION"]) {
            $submitTimeout = $this->user->getSubmitTimeout();
            if ($submitTimeout <= 0) {
                $submitButtons = "
                    <script>
                        function showForm() {
                            showSubmitForm(`{$submitFormContent}`);
                        }
                    </script>
                    <div class='center'>
                        <input type='submit' value='Предай решение' onclick='showForm();' class='button button-large button-color-blue'>
                       <br>
                        <a style='font-size: smaller;' href='/problems/{$problem->getId()}/submits'>Предадени решения</a>
                    </div>
                ";
            } else {
                $submitButtons = "
                    <div class='center'>
                        <span id='submitButtonTooltip' class='tooltip--top' data-tooltip=''>
                            <input type='submit' value='Предай решение' class='button button-large button-color-gray'>
                        </span>
                        <script>setSubmitTimeoutTimer('submitButtonTooltip', $submitTimeout)</script>
                        <br>
                        <a style='font-size: smaller;' href='/problems/{$problem->getId()}/submits'>Предадени решения</a>
                    </div>
                ";
            }
        } else {
            $submitButtons = "
                <div class='center'>
                    <input type='submit' value='Предай решение' class='button button-color-gray button-large'
                        title='Трябва да влезете в системата за да можете да предавате решения.'>
                </div>
            ";
        }

        $visibilityRestriction = $problem->getVisible() ? "" : "<div class='tooltip--bottom' data-tooltip='Задачата е скрита.'><i class='fa fa-eye-slash'></i></div>";
        $statsButton = "<a href='/problems/{$problem->getId()}/stats' style='color: #333333;'><div class='tooltip--top' data-tooltip='информация'><i class='fa fa-info-circle'></i></div></a>";
        $usersButton = "<a href='/problems/{$problem->getId()}/users' style='color: #333333;'><div class='tooltip--top' data-tooltip='потребители'><i class='fa fa-users'></i></div></a>";
        $tagsButton = "<a href='/problems/{$problem->getId()}/tags' style='color: #333333;'><div class='tooltip--top' data-tooltip='тагове'><i class='fa fa-tags'></i></div></a>";
        $pdfButton = "<a href='/problems/{$problem->getId()}/pdf' style='color: #333333;' target='_blank'><div class='tooltip--top' data-tooltip='PDF'><i class='fas fa-file-pdf'></i></div></a>";
        // Remove PDF link for logged-out users (bots tend to click on it and generate PDFs for all problems)
        if ($this->user->getAccess() < $GLOBALS["ACCESS_DOWNLOAD_AS_PDF"]) {
            $pdfButton = "<div class='tooltip--top' data-tooltip='PDF' title='Трябва да влезете в системата за да изтеглите условието като PDF.'><i class='fas fa-file-pdf' style='opacity: 0.5;'></i></div>";
        }
        return "
            <div class='box box-problem'>
                <div class='problem-visibility'>{$visibilityRestriction}</div>
                <div class='problem-title' id='problem-title'>{$problem->getName()}</div>
                <div class='problem-origin'>{$problem->getOrigin()}</div>
                <div class='problem-resources'><b>Time Limit:</b> {$problem->getTimeLimit()}s, <b>Memory Limit:</b> {$problem->getMemoryLimit()}MiB</div>
                <div class='separator'></div>
                <div class='problem-statement'>{$statement}</div>
                {$submitButtons}
                <div class='box-footer'>
                    {$statsButton}
                    &nbsp;
                    {$usersButton}
                    &nbsp;
                    {$tagsButton}
                    &nbsp;
                    {$pdfButton}
                </div>
            </div>
        ";
    }

    private function getPrintStatement(Problem $problem): string {
        $statement = file_get_contents($problem->getStatementPath());
        return "
            <div class='problem-title' id='problem-title'>{$problem->getName()}</div>
            <div class='problem-origin'>{$problem->getOrigin()}</div>
            <div class='problem-resources'><b>Time Limit:</b> {$problem->getTimeLimit()}s, <b>Memory Limit:</b> {$problem->getMemoryLimit()}MiB</div>
            <div class='separator'></div>
            <div class='problem-statement'>{$statement}</div>
        ";
    }

    private function getStatusTable(Submit $submit): string {
        $color = getStatusColor($submit->getStatus());
        $maxExecTime = sprintf("%.2fs", max($submit->getExecTime()));
        $maxExecMemory = sprintf("%.2f MiB", max($submit->getExecMemory()));
        return "
            <table class='default {$color}'>
                <tr>
                    <th>Статус на задачата</th>
                    <th style='width: 100px;'>Време</th>
                    <th style='width: 100px;'>Памет</th>
                    <th style='width: 100px;'>Точки</th>
                </tr>
                <tr>
                    <td>{$GLOBALS['STATUS_DISPLAY_NAME'][$submit->getStatus()]}</td>
                    <td>{$maxExecTime}</td>
                    <td>{$maxExecMemory}</td>
                    <td>{$submit->calcScore()}</td>
                </tr>
            </table>
        ";
    }

    private function getDetailsTable(Submit $submit): string {
        // If compilation error, pretty-print it and return instead of the per-test circles
        if ($submit->getStatus() == $GLOBALS["STATUS_COMPILATION_ERROR"]) {
            return prettyPrintCompilationErrors($submit);
        }

        // Otherwise get information for each test and print it as a colored circle with
        // additional roll-over information
        $points = $submit->calcScores();
        $detailsTable = "";
        for ($i = 1; $i < count($points); $i++) {
            if ($i > 1 && $i % 10 == 1) {
                $detailsTable .= "<br>";
            }
            $result = $submit->getResults()[$i];
            $tooltip =
                "Тест {$i}" . PHP_EOL .
                "Статус: " . (is_numeric($result) ? "OK" : $result) . PHP_EOL .
                "Точки: " . sprintf("%.1f", $points[$i]) . PHP_EOL .
                "Време: " . sprintf("%.2fs", $submit->getExecTime()[$i]) . PHP_EOL .
                "Памет: " . sprintf('%.2f MiB', $submit->getExecMemory()[$i])
            ;
            $icon = "WTF?";
            $background = "";
            if (is_numeric($result)) {
                $background = ($result == 1.0 ? "dull-green" : "dull-teal");
                $icon = "<i class='fa fa-check'></i>";
            } else if (in_array($result, [$GLOBALS["STATUS_WAITING"], $GLOBALS["STATUS_PREPARING"], $GLOBALS["STATUS_COMPILING"]])) {
                $background = "dull-gray";
                $icon = "<i class='fas fa-hourglass-start'></i>";
            } else if ($result == $GLOBALS["STATUS_TESTING"]) {
                $background = "dull-gray";
                $icon = "<i class='fa fa-spinner fa-pulse'></i>";
            } else if ($result == $GLOBALS["STATUS_WRONG_ANSWER"]) {
                $background = "dull-red";
                $icon = "<i class='fa fa-times'></i>";
            } else if ($result == $GLOBALS["STATUS_TIME_LIMIT"]) {
                $background = "dull-red";
                $icon = "<i class='fa fa-clock'></i>";
            } else if ($result == $GLOBALS["STATUS_MEMORY_LIMIT"]) {
                $background = "dull-red";
                $icon = "<i class='fa fa-database'></i>";
            } else if ($result == $GLOBALS["STATUS_RUNTIME_ERROR"]) {
                $background = "dull-red";
                $icon = "<i class='fa fa-bug'></i>";
            } else if ($result == $GLOBALS["STATUS_COMPILATION_ERROR"]) {
                $background = "dull-red";
                $icon = "<i class='fa fa-code'></i>";
            } else if ($result == $GLOBALS["STATUS_INTERNAL_ERROR"]) {
                $background = "dull-black";
                $icon = "<i class='fas fa-exclamation'></i>";
            }
            $detailsTable .= "<div class='test-result tooltip--top background-{$background}' data-tooltip='{$tooltip}'>{$icon}</div>";
        }
        return "
            <div class='test-result-wrapper'>
                <div class='centered'>
                    {$detailsTable}
                </div>
            </div>
        ";
    }

    private function getSource(Problem $problem, int $submitId): void {
        $redirectUrl = getTaskUrl($problem->getId()) . "/submits";
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        echo "<plaintext>{$submit->getSource()}";
        exit(0);
    }

    function getSubmitInfoBoxContent(Problem $problem, int $submitId, string $redirectUrl): string {
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        $statusTable = $this->getStatusTable($submit);
        $detailsTable = $this->getDetailsTable($submit);

        $author = $this->user->getId() != $submit->getUserId() ? "({$submit->getUserName()})" : "";

        $source = getSourceSection($problem, $submit);
        $submitTime = explode(" ", $submit->getSubmitted())[0] . " | " . explode(" ", $submit->getSubmitted())[1];

        return "
            <h2><span class='blue'>{$problem->getName()}</span> :: Статус на решение {$author}</h2>
            <div class='right' style='font-size: smaller'>{$submitTime}</div>
            <br>
            {$statusTable}
            <br>
            {$detailsTable}
            <br>
            {$source}
        ";
    }

    private function getSubmitUpdates(Problem $problem, int $submitId): void {
        $UPDATE_DELAY = 200000; // 0.2s (in microseconds)
        $MAX_UPDATES = 180 * 1000000 / $UPDATE_DELAY; // 180 seconds

        $lastContent = "";
        for ($updateId = 0; $updateId < $MAX_UPDATES; $updateId++) {
            $content = $this->getSubmitInfoBoxContent($problem, $submitId, "");
            if (strcmp($content, $lastContent) != 0) {
                sendServerEventData("content", $content);
                $lastContent = $content;
            }
            // If nothing to wait for, stop the updates
            if (strpos($content, "fa-hourglass-start") === false && strpos($content, "fa-spinner") === false) {
                terminateServerEventStream();
                exit();
            }
            // Stop updating if connection was terminated by the client
            if ($updateId % 10 == 0 && !checkServerEventClient()) {
                exit();
            }
            // Sleep until next check for changes
            usleep($UPDATE_DELAY);
        }
    }

    private function getSubmitInfoBox(Problem $problem, int $submitId): string {
        $redirectUrl = "/problems/{$problem->getId()}/submits";
        if (isset($_SESSION["statusShortcut"]))
            $redirectUrl = "/status";
        $updatesUrl = "/problems/{$problem->getId()}/submits/{$submitId}/updates";

        $content = $this->getSubmitInfoBoxContent($problem, $submitId, $redirectUrl);

        return "
            <script>
                showActionForm(`{$content}`, `{$redirectUrl}`);
                subscribeForUpdates(`{$updatesUrl}`, `action-form-content`);
            </script>
        ";
    }

    private function getAllSubmitsBox(Problem $problem): string {
        $submits = Submit::getAllSubmits($this->user->getId(), $problem->getId());

        $submitList = "";
        for ($i = 1; $i <= count($submits); $i++) {
            $submit = $submits[$i - 1];
            $submitLink = "<a href='/problems/{$problem->getId()}/submits/{$submit->getId()}'>{$submit->getId()}</a>";
            $submitDate = explode(" ", $submit->getSubmitted())[0];
            $submitTime = explode(" ", $submit->getSubmitted())[1];
            $submitList .= "
                <tr>
                    <td>{$i}</td>
                    <td>{$submitLink}</td>
                    <td>{$submitDate}</td>
                    <td>{$submitTime}</td>
                    <td>{$submit->getLanguage()}</td>
                    <td>{$GLOBALS['STATUS_DISPLAY_NAME'][$submit->calcStatus()]}</td>
                    <td>" . round($submit->calcScore(), 3) . "</td>
                </tr>
            ";
        }

        $content = "
            <h2><span class='blue'>{$problem->getName()}</span> :: Ваши решения</h2>
            <table class='default'>
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>Дата</th>
                    <th>Час</th>
                    <th>Език</th>
                    <th>Статус</th>
                    <th>Точки</th>
                </tr>
                {$submitList}
            </table>
        ";

        $returnUrl = "/problems/{$problem->getId()}";
        return "
            <script>
                showActionForm(`{$content}`, `{$returnUrl}`);
            </script>
        ";
    }

    public function getContent(): string {
        $statusShortcut = false;
        if (isset($_SESSION["statusShortcut"])) {
            $statusShortcut = true;
            unset($_SESSION["statusShortcut"]);
        }

        $this->problemName = null;
        if (isset($_GET["problemId"])) {
            $problem = Problem::get($_GET["problemId"]);
            if ($problem == null) {
                redirect("/problems", "ERROR", "Няма задача с такъв идентификатор.");
            }
            $this->problemName = $problem->getName();
            if (!canSeeProblem($this->user, $problem->getVisible())) {
                redirect("/problems", "ERROR", "Нямате права да видите тази задача.");
            }
            if ($problem->getType() == "game" || $problem->getType() == "relative") {
                redirect(str_replace(getTaskUrl($problem->getId()), getGameUrl($problem->getName()), getCurrentUrl()));
            }

            $content = $this->getStatement($problem);
            if (isset($_GET["submits"])) {
                if ($this->user->getId() == -1) {
                    redirect("/problems/{$problem->getId()}", "ERROR", "Трябва да влезете в профила си за да видите тази страница.");
                }
                if (!isset($_GET["submitId"])) {
                    $content .= $this->getAllSubmitsBox($problem);
                } else {
                    if (isset($_GET["source"])) {
                        $this->getSource($problem, $_GET["submitId"]);
                    } else if (isset($_GET["updates"])) {
                        $this->getSubmitUpdates($problem, $_GET["submitId"]);
                    } else {
                        if ($statusShortcut)
                            $_SESSION["statusShortcut"] = true;
                        $content .= $this->getSubmitInfoBox($problem, $_GET["submitId"]);
                    }
                }
            } else if (isset($_GET["stats"])) {
                $content .= $this->getStatsBox($problem);
            } else if (isset($_GET["users"])) {
                $content .= $this->getUsersBox($problem);
            } else if (isset($_GET["tags"])) {
                $content .= $this->getTagsBox($problem);
            } else if (isset($_GET["print"])) {
                $content = $this->getPrintStatement($problem);
            } else if (isset($_GET["pdf"])) {
                // Disallow this action for signed-out users.
                if ($this->user->getAccess() < $GLOBALS["ACCESS_DOWNLOAD_AS_PDF"]) {
                    redirect("/problems/{$problem->getId()}", "ERROR", "Трябва да влезете в системата за да изтеглите условието.");
                } else {
                    return_pdf_file($problem);
                }
            }
            return $content;
        }
        return $this->getMainPage();
    }
}

?>