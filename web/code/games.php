<?php
require_once("actions/print_pdf.php");
require_once("db/brain.php");
require_once("entities/problem.php");
require_once("entities/submit.php");
require_once("config.php");
require_once("common.php");
require_once("page.php");
require_once("events.php");

class GamesPage extends Page {

    public function getTitle(): string {
        return "O(N)::Games";
    }

    /** @return string[] */
    public function getExtraScripts(): array {
        return array(
            "/scripts/language_detector.js",
            "/scripts/games/snakes.js",
            "/scripts/games/uttt.js",
            "/scripts/games/hypersnakes.js",
            "/scripts/games/tetris.js",
            "/scripts/games/connect.js",
            "/scripts/games/imagescanner.js",
            "/scripts/jquery-3.3.1.min.js",
            "/scripts/jquery-jvectormap-2.0.3.min.js",
            "/scripts/jquery-jvectormap-world-mill.js"
        );
    }

    /** @return string[] */
    public function getExtraStyles(): array {
        return array(
            "/styles/tooltips.css",
            "/styles/games.css",
            "/styles/jquery-jvectormap-2.0.3.css"
        );
    }

    public function onLoad(): string {
        return "addPreTags()";
    }

    public static function getGameRanking(Problem $problem): array {
        // Returns a sorted array of data:
        // {
        //     "userId": <int>,
        //     "submitId": <int>,
        //     "score": <float>
        // }
        // The array is sorted from best to worst.
        // A user is ranked better than another user, if he/she has more points or has submitted earlier.

        $matches = Match::getGameMatches($problem->getId());

        $submit = array();
        $playerScore = array();
        $opponentScore = array();

        // Initialize all arrays with zeroes
        foreach ($matches as $match) {
            // If one of the users is negative, this means this is a partial submission match.
            if ($match->getUserOne() < 0 || $match->getUserTwo() < 0)
                continue;
            for ($player = 0; $player < 2; $player += 1) {
                $userKey = $player == 0 ? "User_{$match->getUserOne()}" : "User_{$match->getUserTwo()}";
                $submit[$userKey] = $playerScore[$userKey] = $opponentScore[$userKey] = 0;
            }
        }

        // Get the scores, wins, draws, and losses for each player
        foreach ($matches as $match) {
            // If one of the users is negative, this means this is a partial submission match.
            if ($match->getUserOne() < 0 || $match->getUserTwo() < 0)
                continue;

            // Don't even get me started on why we must prepend with "User_"...
            $userOneKey = "User_{$match->getUserOne()}";
            $userTwoKey = "User_{$match->getUserTwo()}";

            $scoreUserOne = $match->getScoreOne();
            $scoreUserTwo = $match->getScoreTwo();

            // TODO: Fix this in a better way (add scoreWin and scoreTie to each game)
            if ($problem->getName() == "Ultimate TTT") {
                if ($scoreUserOne > $scoreUserTwo) $scoreUserOne = 3;
                if ($scoreUserTwo > $scoreUserOne) $scoreUserTwo = 3;
            }

            // Player one scores
            $submit[$userOneKey] = $match->getSubmitOne();
            $playerScore[$userOneKey] += $scoreUserOne;
            $opponentScore[$userOneKey] += $scoreUserTwo;

            // Player two scores
            $submit[$userTwoKey] = $match->getSubmitTwo();
            $playerScore[$userTwoKey] += $scoreUserTwo;
            $opponentScore[$userTwoKey] += $scoreUserOne;
        }

        $ranking = array();
        $numPlayers = count($playerScore);
        for ($pos = 0; $pos < $numPlayers; $pos++) {
            $bestUser = "";
            $maxPlayerScore = -1;
            $minOpponentScore = 1e100;

            foreach ($playerScore as $userKey => $score) {
                $isBetter = ($maxPlayerScore < $playerScore[$userKey]) ||
                            ($maxPlayerScore == $playerScore[$userKey] && $minOpponentScore > $opponentScore[$userKey]) ||
                            ($maxPlayerScore == $playerScore[$userKey] && $minOpponentScore == $opponentScore[$userKey] && $submit[$userKey] < $submit[$bestUser]);
                if ($isBetter) {
                    $bestUser = $userKey;
                    $maxPlayerScore = $playerScore[$userKey];
                    $minOpponentScore = $opponentScore[$userKey];
                }
            }

            array_push($ranking, array(
                "userId" => intval(substr($bestUser, 5)),
                "submitId" => $submit[$bestUser],
                "score" => $playerScore[$bestUser],
            ));
            unset($playerScore[$bestUser]);
        }
        return $ranking;
    }

    private static function populateRelativePoints(Problem $problem, array &$bestScores, array &$userSubmits): void {
        $submits = Submit::getProblemSubmits($problem->getId());
        // Take into account only the latest submission of each user
        $submits = array_reverse($submits);

        foreach ($submits as $submit) {
            // Skip system submits
            if ($submit->getUserId() == 0) {
                continue;
            }
            $userKey = "User_{$submit->getUserId()}";
            if (array_key_exists($userKey, $userSubmits)) {
                continue;
            }
            $userSubmits[$userKey] = $submit;

            // TODO: Refactor the code below to use a Problem property which is min/max
            //       (whether the goal is to minimize or maximize the score)
            for ($i = 0; $i < count($submit->getResults()); $i++) {
                if (count($bestScores) <= $i) {
                    if ($problem->getName() == "ImageScanner") {
                        array_push($bestScores, 1e100);
                    } else {
                        array_push($bestScores, 0.0);
                    }
                }
                $curScore = $submit->getResults()[$i];
                if (is_numeric($curScore)) {
                    if ($problem->getName() == "ImageScanner") {
                        if ($bestScores[$i] > $curScore) {
                            $bestScores[$i] = $curScore;
                        }
                    } else {
                        if ($bestScores[$i] < $curScore) {
                            $bestScores[$i] = $curScore;
                        }
                    }
                }
            }
        }

        // Hack to make Airports use the grader's score
        // This, in fact, makes the task just a standard problem, but, oh well, I needed it to be a game.
        // This will be fixed once we have a "scoring" field in the Problem structure.
        if ($problem->getName() == "Airports") {
            for ($i = 0; $i < count($bestScores); $i++)
                $bestScores[$i] = 1.0;
        }
    }

    private static function getPartialScore(string $problemName, string $testScore, float $bestScore, float $scoringPower): float {
        $fraction = 0.0;
        if (is_numeric($testScore)) {
            $testScore = toFloat($testScore);
            if ($problemName == "ImageScanner") {
                $fraction = $testScore <= $bestScore ? 1.0 : pow($bestScore / $testScore, $scoringPower);
            } else {
                $fraction = $testScore >= $bestScore ? 1.0 : pow($testScore / $bestScore, $scoringPower);
            }
        }
        return $fraction;
    }

    public static function getRelativeRanking(Problem $problem): array {
        // Returns a sorted array of data:
        // {
        //     "userId": <int>,
        //     "submitId": <int>,
        //     "score": <float>
        // }
        // The array is sorted from best to worst.
        // A user is ranked better than another user, if he/she has more points or has submitted earlier.

        $bestScores = array();
        $userSubmits = array();
        GamesPage::populateRelativePoints($problem, $bestScores, $userSubmits);

        $testScore = 100.0 / (count($bestScores) - 1);

        // TODO: Make the formula be configurable per problem
        $scoringPower = 1.0;
        if ($problem->getName() == "HyperWords")
            $scoringPower = 2.0;

        $ranking = array();
        foreach ($userSubmits as $userKey => $submit) {
            /* @type Submit $submit */
            $score = 0.0;
            for ($i = 1; $i < count($submit->getResults()); $i++) {
                $fraction = GamesPage::getPartialScore($problem->getName(), $submit->getResults()[$i], $bestScores[$i], $scoringPower);
                $score += $fraction * $testScore;
            }
            $ranking[] = array(
                "userId" => intval(substr($userKey, 5)),
                "submitId" => $submit->getId(),
                "score" => $score,
            );
        }

        usort($ranking, function($user1, $user2) {
            if ($user1["score"] != $user2["score"]) {
                return $user1["score"] < $user2["score"] ? +1 : -1;
            } else {
                return $user1["submitId"] < $user2["submitId"] ? -1 : +1;
            }
        });
        return $ranking;
    }

    private function getGamesList(): string {
        $problems = Problem::getAllGames();
        // Show newest games first
        $problems = array_reverse($problems);

        $gameList = "";
        // Calculate statistics (position and points) for each game for this user
        foreach ($problems as $problem) {
            // Don't show hidden games
            if (!canSeeProblem($this->user, $problem->getVisible()))
                continue;

            $ranking = $problem->getType() == "game" ? $this->getGameRanking($problem) :
                                                       $this->getRelativeRanking($problem);

            $position = 0;
            for (; $position < count($ranking); $position += 1)
                if ($ranking[$position]["userId"] == $this->user->getId())
                    break;
            $scoreStr = $positionStr = "N/A";
            if ($position < count($ranking)) {
                $scoreStr = sprintf("%.2f (best is %.2f)",
                    $ranking[$position]["score"], $ranking[0]["score"]);
                $positionStr = sprintf("%d (out of %d)", $position + 1, count($ranking));
            }

            $stats = "
                <i class='fa fa-trophy'></i> Position: {$positionStr}
                &nbsp;&nbsp;
                <i class='fa fa-star'></i> Score: {$scoreStr}
            ";

            $gameBox = "
                <a href='/games/" . getGameUrlName($problem->getName()) . "' class='decorated'>
                    <div class='box narrow boxlink'>
                        <div class='game-info'>
                            <div class='game-name'>{$problem->getName()}</div>
                            <div class='game-stats'>{$stats}</div>
                            <div class='game-description'>{$problem->getDescription()}</div>
                        </div>
                        <div class='game-image'><img alt='Game Image' class='game-image' src='{$problem->getLogo()}'></div>
                    </div>
                </a>
            ";
            // Make hidden games grayed out (actually visible only to admins).
            if (!$problem->getVisible()) {
                $gameBox = "<div style='opacity: 0.5;'>{$gameBox}</div>";
            }
            $gameList .= $gameBox;
        }
        return $gameList;
    }

    private function getMainPage(): string {
        $header = inBox("
            <h1>Игри</h1>
            Тук можете да намерите няколко игри, за които трябва да напишете изкуствен интелект.
        ");
        return $header . $this->getGamesList();
    }

    private function getVisualizerButton(Problem $problem): ?string {
        if (in_array($problem->getName(), ["HyperWords", "Airports", "ImageScanner", "NumberGuessing"]))
            return null;

        $url = getGameUrl($problem->getName()) . "/visualizer";
        return "
            <a href='{$url}'>
                <input type='submit' value='Визуализатор' class='button button-color-blue button-large' title='Визуализатор на играта'>
            </a>
        ";
    }

    private function getScoreboardButton(Problem $problem): ?string {
        if (in_array($problem->getName(), ["NumberGuessing", "OtherNonRelativeGames"]))
            return null;

        $url = getGameUrl($problem->getName()) . "/scoreboard";
        return "
            <a href='{$url}'>
                <input type='submit' value='Класиране' class='button button-color-blue button-small' title='Класиране на всички участници'>
            </a>
        ";
    }

    private function getSeeSubmissionsLink(Problem $problem): ?string {
        if ($this->user->getAccess() < $GLOBALS["ACCESS_SUBMIT_SOLUTION"])
            return null;

        $url = getGameUrl($problem->getName()) . "/submits";
        return "
            <a style='font-size: smaller;' href='{$url}'>Предадени решения</a>
        ";
    }

    private function getSubmitButton(string $buttonText, string $buttonTooltip, string $buttonFormName, string $buttonFormContent, int $buttonTimeout): string {
        if ($this->user->getAccess() >= $GLOBALS["ACCESS_SUBMIT_SOLUTION"]) {
            if ($buttonTimeout <= 0) {
                return "
                    <script>function {$buttonFormName}() {showSubmitForm(`{$buttonFormContent}`);}</script>
                    <input type='submit' class='button button-large button-color-blue' value='{$buttonText}'
                           title='{$buttonTooltip}' onclick='{$buttonFormName}();'>
                ";
            } else {
                return "
                    <input type='submit' class='button button-large button-color-gray' value='{$buttonText}'
                           title='Ще можете да предадете отново след {$buttonTimeout} секунди.'>
                ";
            }
        } else {
            return "
                <input type='submit' class='button button-large button-color-gray' value='{$buttonText}'
                       title='Трябва да влезете в системата за да можете да предавате решения.'>
            ";
        }
    }

    private function getSubmitFormContent(Problem $problem, string $formTitle, string $formText, bool $isFull): string {
        $isFullText = $isFull ? "true" : "false";
        return "
            <h2><span class='blue'>{$problem->getName()}</span> :: {$formTitle}</h2>
            <div class='center'>{$formText}</div>
            <br>
            <div class='center'>
                <textarea name='source' class='submit-source' cols=80 rows=24 maxlength=50000 id='source'></textarea>
            </div>
            <div class='italic right' style='font-size: 0.8em;'>Detected language: <span id='language'>?</span></div>
            <div class='center'><input type='submit' value='Изпрати' onclick='submitSubmitForm({$problem->getId()}, {$isFullText});' class='button button-color-red'></div>
        ";
    }

    private function getStatementBox(Problem $problem, ?string $partSubmitButton, ?string $fullSubmitButton): string {
        $statement = file_get_contents($problem->getStatementPath());

        $visualizerButton = $this->getVisualizerButton($problem);
        $scoreboardButton = $this->getScoreboardButton($problem);
        $seeSubmissionsLink = $this->getSeeSubmissionsLink($problem);

        $buttons = "";
        if ($partSubmitButton) $buttons .= $partSubmitButton;
        if ($visualizerButton) $buttons .= $visualizerButton;
        if ($fullSubmitButton) $buttons .= $fullSubmitButton;
        if ($seeSubmissionsLink) $buttons .= "<br>" . $seeSubmissionsLink;
        if ($scoreboardButton) $buttons .= "<br>" . $scoreboardButton;


        $visibilityRestriction = $problem->getVisible() ? "" : "<div class='tooltip--bottom' data-tooltip='Задачата е скрита.'><i class='fa fa-eye-slash'></i></div>";
        return "
            <div class='box box-problem'>
                <div class='problem-visibility'>{$visibilityRestriction}</div>
                <div class='problem-title' id='problem-title'>{$problem->getName()}</div>
                <div class='problem-origin'>{$problem->getOrigin()}</div>
                <div class='problem-resources'><b>Time Limit:</b> {$problem->getTimeLimit()}s, <b>Memory Limit:</b> {$problem->getMemoryLimit()}MiB</div>
                <div class='separator'></div>
                <div class='problem-statement'>
                    {$statement}
                </div>
                <div class='center'>
                    {$buttons}
                </div>
                <div class='box-footer'>
                    <a href='" . getGameUrl($problem->getName()) . "/pdf' style='color: #333333;' target='_blank'>
                        <div class='tooltip--top' data-tooltip='PDF'>
                            <i class='fas fa-file-pdf'></i>
                        </div>
                    </a>
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
            <div class='problem-statement'>
                {$statement}
            </div>
        ";
    }

    private function getGameStatement(Problem $problem): string {
        $partSubmitText = "Частично решение";
        $partSubmitInfo = "Частичното решение се тества срещу няколко авторски решения с различна сложност и не се запазва като финално.";
        if ($problem->getWaitPartial() > 0) {
            $partSubmitInfo .= "
Можете да предавате такова решение веднъж на всеки {$problem->getWaitPartial()} минути.";
        }
        $fullSubmitText = "Пълно решение";
        $fullSubmitInfo = "Пълното решение се тества срещу всички решения и се запазва като финално (дори да сте предали по-добро по-рано).";
        if ($problem->getWaitFull() > 0) {
            $fullSubmitInfo .= "
Можете да предавате такова решение веднъж на всеки {$problem->getWaitFull()} минути.";
        }

        $partTimeout = 0; $fullTimeout = 0;
        getWaitingTimes($this->user, $problem, $partTimeout, $fullTimeout);
        $partSubmitContent = $this->getSubmitFormContent($problem, $partSubmitText, $partSubmitInfo, false);
        $partSubmitButton = $this->getSubmitButton($partSubmitText, $partSubmitInfo, "showPartForm", $partSubmitContent, $partTimeout);
        $fullSubmitContent = $this->getSubmitFormContent($problem, $fullSubmitText, $fullSubmitInfo, true);
        $fullSubmitButton = $this->getSubmitButton($fullSubmitText, $fullSubmitInfo, "showFullForm", $fullSubmitContent, $fullTimeout);
        return $this->getStatementBox($problem, $partSubmitButton, $fullSubmitButton);
    }

    private function getRelativeStatement(Problem $problem): string {
        $fullSubmitText = "Изпрати решение";
        $fullSubmitInfo = "Решението ще получи пропорционални точки спрямо авторското решение, или това на най-добрия друг участник.";

        $partTimeout = 0; $fullTimeout = 0;
        getWaitingTimes($this->user, $problem, $partTimeout, $fullTimeout);
        $fullSubmitContent = $this->getSubmitFormContent($problem, $fullSubmitText, $fullSubmitInfo, true);
        $fullSubmitButton = $this->getSubmitButton($fullSubmitText, $fullSubmitInfo, "showFullForm", $fullSubmitContent, $fullTimeout);
        return $this->getStatementBox($problem, null, $fullSubmitButton);
    }

    private function getGameStatusTable(Submit $submit, string $status, bool $found, float $totalScoreUser, float $totalScoreOpponents): string {
        $color = getStatusColor($status);
        // An old submit, no score for it
        if ($color == "green" && $found == false)
            $color = "gray";

        if ($found) {
            $score = sprintf("<span>%.0f:%.0f</span>", $totalScoreUser, $totalScoreOpponents);
        } else {
            $score = "<span title='Точки се изчисляват само за последното изпратено решение.'>-</span>";
        }

        $maxExecTime = sprintf("%.2fs", max($submit->getExecTime()));
        $maxExecMemory = sprintf("%.2f MiB", max($submit->getExecMemory()));
        return "
            <table class='default {$color}'>
                <tr>
                    <th>Статус на задачата</th>
                    <th style='width: 6.125rem;'>Време</th>
                    <th style='width: 6.125rem;'>Памет</th>
                    <th style='width: 6.125rem;'>Резултат</th>
                </tr>
                <tr>
                    <td>{$GLOBALS['STATUS_DISPLAY_NAME'][$status]}</td>
                    <td>{$maxExecTime}</td>
                    <td>{$maxExecMemory}</td>
                    <td>{$score}</td>
                </tr>
            </table>
        ";
    }

    private function getGameDetailsTable(Problem $problem, Submit $submit, string $status, bool $found, array $games, int $matchesPerGame): string {
        // If compilation error, pretty-print it and return instead of the per-match circles
        if ($status == $GLOBALS['STATUS_COMPILATION_ERROR']) {
            return prettyPrintCompilationErrors($submit);
        }

        // If there is no information about this submission, then there is a newer submitted solution
        // Let the user know that only the latest submissions are being kept as official
        if (!$found) {
            return "
                <div class='centered italic'>
                    Имате по-ново предадено решение.<br>
                    Точки се изчисляват само за последното изпратено такова.
                </div>
            ";
        }

        $matchColumns = "";
        for ($i = 1; $i <= $matchesPerGame; $i++) {
            $matchColumns .= "
                    <th style='width: 5%;'>{$i}</th>
            ";
        }
        $details = "";
        ksort($games);
        foreach($games as $opponentKey => $results) {
            $opponentId = intval(explode("_", $opponentKey)[1]);
            $opponentLink = "";
            if ($opponentId < 0) {
                $opponentLink = sprintf("Author%d", -$opponentId);
            } else {
                $opponent = User::getById($opponentId);
                $opponentLink = getUserLink($opponent->getUsername());
            }

            $scoreUser = 0;
            $scoreOpponent = 0;
            $perTestStatus = "";
            foreach ($results as $result) {
                $scoreUser += $result["scoreUser"];
                $scoreOpponent += $result["scoreOpponent"];
                $message = htmlspecialchars($result["message"], ENT_QUOTES);
                $showLink = true;

                if ($result["scoreUser"] > $result["scoreOpponent"]) {
                    // Win
                    $classes = "far fa-check-circle green";
                } else if ($result["scoreUser"] < $result["scoreOpponent"]) {
                    // Loss
                    $classes = "far fa-times-circle red";
                } else {
                    if ($result["scoreUser"] == 0 && $result["scoreOpponent"] == 0) {
                        // Not played
                        $classes = "far fa-question-circle gray";
                        $message = "Мачът още не е изигран.";
                        $showLink = false;
                    } else {
                        // Draw
                        $classes = "far fa-pause-circle yellow";
                    }
                }
                $testStatus = "<i class='{$classes}' title='{$message}'></i>";
                if ($showLink) {
                    $testStatus = "
                        <a href='" . getGameUrl($problem->getName()) . "/submits/{$submit->getId()}/replays/{$result['id']}'>
                            {$testStatus}
                        </a>
                    ";
                }
                $perTestStatus .= "
                    <td>{$testStatus}</td>
                ";
            }
            $scoreResults = sprintf("%.0f:%.0f", $scoreUser, $scoreOpponent);
            $details .= "
                <tr>
                    <td>{$opponentLink}</td>
                    <td>{$scoreResults}</td>
                    {$perTestStatus}
                </tr>
            ";
        }

        return "
            <table class='default blue'>
                <tr>
                    <th style='width: 28%;'>Опонент</th>
                    <th style='width: 12%;'>Резултат</th>
                    {$matchColumns}
                </tr>
                {$details}
            </table>
        ";
    }

    private function getSubmitInfoBoxContent(Problem $problem, Submit $submit, string $statusTable, string $detailsTable): string {
        $author = "";
        if ($this->user->getId() != $submit->getUserId()) {
            $author = "({$submit->getUserName()})";
        }

        $source = getSourceSection($problem, $submit);
        $submitDate = explode(" ", $submit->getSubmitted())[0];
        $submitTime = explode(" ", $submit->getSubmitted())[1];

        return "
            <h2><span class='blue'>{$problem->getName()}</span> :: Статус на решение {$author}</h2>
            <div class='right' style='font-size: smaller'>{$submitDate} | {$submitTime}</div>
            <br>
            {$statusTable}
            <br>
            {$detailsTable}
            <br>
            {$source}
        ";
    }

    private function getGameSubmitInfoBoxContent(Problem $problem, int $submitId, string $redirectUrl): string {
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        $status = $submit->calcStatus();

        $matches = Match::getGameMatches($problem->getId(), $submit->getUserId());

        $matchesPerGame = count($submit->getResults()) * 2;

        $found = false;
        $games = array();
        $totalScoreUser = 0;
        $totalScoreOpponents = 0;
        foreach ($matches as $match) {
            $opponentId = $submit->getUserId() == $match->getUserOne() ? $match->getUserTwo() : $match->getUserOne();
            // If submit is full we care only about matches against actual users
            // If submit is partial we care only about matches against author's solutions
            if (($submit->getFull() && $opponentId < 0) || (!$submit->getFull() && $opponentId > 0))
                continue;

            $userSubmit = $submit->getUserId() == $match->getUserOne() ? $match->getSubmitOne() : $match->getSubmitTwo();
            // If not the target submit, skip it
            if ($userSubmit != $submit->getId())
                continue;

            $found = true;
            $opponentKey = "User_{$opponentId}";
            if (!array_key_exists($opponentKey, $games))
                $games[$opponentKey] = array();

            $scoreUser = $submit->getUserId() == $match->getUserOne() ? $match->getScoreOne() : $match->getScoreTwo();
            $scoreOpponent = $submit->getUserId() == $match->getUserOne() ? $match->getScoreTwo() : $match->getScoreOne();

            // TODO: Fix this in a better way (add scoreWin and scoreTie to each game)
            if ($problem->getName() == "Ultimate TTT") {
                if ($scoreUser > $scoreOpponent) $scoreUser = 3;
                if ($scoreOpponent > $scoreUser) $scoreOpponent = 3;
            }

            if ($match->getTest() >= 0) {
                $totalScoreUser += $scoreUser;
                $totalScoreOpponents += $scoreOpponent;
                array_push($games[$opponentKey], array(
                    "id" => $match->getId(),
                    "scoreUser" => $scoreUser,
                    "scoreOpponent" => $scoreOpponent,
                    "message" => $match->getMessage(),
                    "replayKey" => $match->getReplayKey()
                ));
            }
        }

        $statusTable = $this->getGameStatusTable($submit, $status, $found, $totalScoreUser, $totalScoreOpponents);
        $detailsTable = $this->getGameDetailsTable($problem, $submit, $status, $found, $games, $matchesPerGame);

        return $this->getSubmitInfoBoxContent($problem, $submit, $statusTable, $detailsTable);
    }

    private function getRelativeStatusTable(Submit $submit, array $points): string {
        $color = getStatusColor($submit->getStatus());
        $maxExecTime = sprintf("%.2fs", max($submit->getExecTime()));
        $maxExecMemory = sprintf("%.2f MiB", max($submit->getExecMemory()));
        $score = sprintf("%.3f", array_sum($points) - $points[0]);
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
                    <td>{$score}</td>
                </tr>
            </table>
        ";
    }

    private function getRelativeDetailsTable(Problem $problem, Submit $submit, array $points): string {
        // If compilation error, pretty-print it and return instead of the per-test circles
        if ($submit->getStatus() == $GLOBALS["STATUS_COMPILATION_ERROR"]) {
            return prettyPrintCompilationErrors($submit);
        }

        if ($submit->getProblemName() == "Airports") {
            if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"]) {
                return "<div id='world-map' style='width: 43rem; height: 20rem;'></div>";
            }
        }

        // Otherwise get information for each test and print it as a colored circle with
        // additional roll-over information
        $testResults = "";
        for ($i = 1; $i < count($points); $i++) {
            if ($i > 1 && $i % 10 == 1) {
                $testResults .= "<br>";
            }
            $result = $submit->getResults()[$i];
            $tooltip =
                "Тест " . $i . PHP_EOL .
                "Статус: " . (is_numeric($result) ? "OK" : $result) . PHP_EOL .
                "Точки: " . sprintf("%.1f", $points[$i]) . " ({$result})" . PHP_EOL .
                "Време: " . sprintf("%.2fs", $submit->getExecTime()[$i]) . PHP_EOL .
                "Памет: " . sprintf("%.2f MiB", $submit->getExecMemory()[$i])
            ;

            $icon = "WTF?";
            $background = "";
            if (is_numeric($result)) {
                $maxPoints = 100.0 / (count($points) - 1);
                $background = (abs($points[$i] - $maxPoints) < 0.001 ? "dull-green" : "dull-teal");
                $icon = "<i class='fa fa-check'></i>";
            } else if ($result == $GLOBALS["STATUS_WAITING"] || $result == $GLOBALS["STATUS_PREPARING"] || $result == $GLOBALS["STATUS_COMPILING"]) {
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

            $testCircle = "<div class='test-result tooltip--top background-{$background}' data-tooltip='{$tooltip}'>{$icon}</div>";
            if ($problem->getName() == "ImageScanner") {
                $testCircle = str_replace("test-result", "test-result test-result-link", $testCircle);
                $testResultUrl = getGameUrl($problem->getName()) . "/submits/{$submit->getId()}/replays/{$i}";
                $testCircle = "<a href='{$testResultUrl}'>{$testCircle}</a>";
            }
            $testResults .= $testCircle;
        }
        return "
            <div class='test-result-wrapper'>
                <div class='centered'>
                    {$testResults}
                </div>
            </div>
        ";
    }

    function getRelativeSubmitInfoBoxContent(Problem $problem, int $submitId, string $redirectUrl): string {
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);

        $bestScores = array();
        $userSubmits = array();
        $this->populateRelativePoints($problem, $bestScores, $userSubmits);

        // TODO: Make the formula to be configurable per problem
        $scoringPower = 1.0;
        if ($problem->getName() == "HyperWords")
            $scoringPower = 2.0;

        $points = array();
        $testWeight = 100.0 / (count($bestScores) - 1);
        for ($i = 0; $i < count($bestScores); $i++) {
            $fraction = GamesPage::getPartialScore($problem->getName(), $submit->getResults()[$i], $bestScores[$i], $scoringPower);
            array_push($points, $fraction * $testWeight);
        }

        $statusTable = $this->getRelativeStatusTable($submit, $points);
        $detailsTable = $this->getRelativeDetailsTable($problem, $submit, $points);

        return $this->getSubmitInfoBoxContent($problem, $submit, $statusTable, $detailsTable);
    }

    private function getSubmitUpdates(Problem $problem, int $submitId): void {
        $UPDATE_DELAY = 500000; // 0.5s (in microseconds)
        $MAX_UPDATES = 180 * 1000000 / $UPDATE_DELAY; // 180 seconds

        $lastContent = "";
        for ($updateId = 0; $updateId < $MAX_UPDATES; $updateId++) {
            $content = "";
            if ($problem->getType() == "game") {
                $content = $this->getGameSubmitInfoBoxContent($problem, $submitId, "");
            } else if ($problem->getType() == "relative" || $problem->getType() == "interactive") {
                $content = $this->getRelativeSubmitInfoBoxContent($problem, $submitId, "");
            }

            if (strcmp($content, $lastContent) != 0) {
                sendServerEventData("content", $content);
                $lastContent = $content;
            }
            // If nothing to wait for, stop the updates
            $allTested = strpos($content, "fa-hourglass-start") === false &&
                         strpos($content, "fa-spinner") === false &&
                         strpos($content, "fa-question-circle") == false;
            if ($allTested) {
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

    private function getAirportsScript(Problem $problem, int $submitId, string $redirectUrl): string {
        $airportData = explode("\n", trim(
            file_get_contents("{$GLOBALS["PATH_DATA"]}/problems/Games.Airports/Misc/AirportData.csv"))
        );
        $airportInfo = array();
        for ($i = 1; $i < count($airportData); $i++) {
            $line = explode(",", trim($airportData[$i]));
            $airportInfo[$line[0]] = array($line[1], $line[2]);
        }

        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        $airports = explode(",", trim($submit->getInfo()));
        $content = "var data = [";
        foreach ($airports as $airport) {
            $content .= "{name: '{$airport}', latLng:[{$airportInfo[$airport][0]}, {$airportInfo[$airport][1]}]},";
        }
        $content .= '];' . PHP_EOL;
        $content .= "      $(function() {
            $('#world-map').vectorMap({
                map: 'world_mill',
                backgroundColor: 'white',
                zoomMin: 1,
                zoomMax: 1,
                regionStyle: {
                    initial: {
                        fill: '#9A9A9A',
                        'fill-opacity': 1,
                        stroke: 'none',
                        'stroke-width': 0,
                        'stroke-opacity': 1
                    },
                    hover: {
                        'fill-opacity': 0.8,
                        cursor: 'pointer'
                    },
                },
                markerStyle: {
                    initial: {
                        fill: '#129D5A',
                        stroke: '#505050',
                        'fill-opacity': 1,
                        'stroke-width': 1,
                        'stroke-opacity': 1,
                        r: 5
                    },
                    hover: {
                        stroke: '#333333',
                        'stroke-width': 2,
                        cursor: 'pointer'
                    }
                },
                markers: data
            });
        });";
        return $content;
    }

    private function getSource(Problem $problem, int $submitId): void {
        $redirectUrl = getGameUrl($problem->getName()) . "/submits";
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        echo "<plaintext>{$submit->getSource()}";
        exit(0);
    }

    private function getSubmitInfoBox(Problem $problem, int $submitId): string {
        $redirectUrl = getGameUrl($problem->getName()) . "/submits";
        if (isset($_SESSION["statusShortcut"]))
            $redirectUrl = "/status";

        if ($problem->getType() == "game") {
            $content = $this->getGameSubmitInfoBoxContent($problem, $submitId, $redirectUrl);
            return "
                <script>
                    showActionForm(`{$content}`, `{$redirectUrl}`);
                </script>
            ";
        } else if ($problem->getType() == "relative" || $problem->getType() == "interactive") {
            $content = $this->getRelativeSubmitInfoBoxContent($problem, $submitId, $redirectUrl);
            if ($problem->getName() != "Airports") {
                $updatesUrl = getGameUrl($problem->getName()) . "/submits/{$submitId}/updates";
                return "
                    <script>
                        showActionForm(`{$content}`, `{$redirectUrl}`);
                        subscribeForUpdates(`{$updatesUrl}`, `action-form-content`);
                    </script>
                ";
            } else {
                return "
                    <script>
                        showActionForm(`{$content}`, `{$redirectUrl}`);
                        " . $this->getAirportsScript($problem, $submitId, $redirectUrl) . "
                    </script>
                ";
            }
        } else {
            error_log("ERROR: In games, but problem is neither 'game' nor 'relative'!");
        }
        return "";
    }

    private function getAllSubmitsBox(Problem $problem): string {
        $gameUrl = getGameUrl($problem->getName());
        $submits = Submit::getAllSubmits($this->user->getId(), $problem->getId());

        $finalFull = -1;
        foreach ($submits as $submit) {
            if ($submit->getFull()) {
                $finalFull = max($finalFull, $submit->getId());
            }
        }

        $submitList = "";
        for ($i = 1; $i <= count($submits); $i++) {
            $submit = $submits[$i - 1];
            $submitLink = "<a href='{$gameUrl}/submits/{$submit->getId()}'>{$submit->getId()}</a>";
            $submitDate = explode(" ", $submit->getSubmitted())[0];
            $submitTime = explode(" ", $submit->getSubmitted())[1];
            $finalSubmitIcon = ($finalFull == $submit->getId() ? "<i class='fa fa-check green'></i>" : "");
            $submitList .= "
                <tr>
                    <td>{$i}</td>
                    <td>{$submitLink}</td>
                    <td>{$submitDate}</td>
                    <td>{$submitTime}</td>
                    <td>{$submit->getLanguage()}</td>
                    <td>{$GLOBALS['STATUS_DISPLAY_NAME'][$submit->calcStatus()]}</td>
                    <td>{$finalSubmitIcon}</td>
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
                    <th>Финално</th>
                </tr>
                {$submitList}
            </table>
        ";

        return "
            <script>
                showActionForm(`{$content}`, `{$gameUrl}`);
            </script>
        ";
    }

    private function getReplayFunction(string $gameName): string {
        if ($gameName == "snakes") return "showSnakesReplay";
        if ($gameName == "ultimate-ttt") return "showUtttReplay";
        if ($gameName == "hypersnakes") return "showHypersnakesReplay";
        if ($gameName == "connect") return "showConnectReplay";
        if ($gameName == "imagescanner") return "showImagescannerReplay";
        return "undefinedFunction";
    }

    private function getGameReplay(Problem $problem, int $submitId, int $matchId): string {
        $returnUrl = getGameUrl($problem->getName()) . "/submits/{$submitId}";
        $match = Match::getById($matchId);

        // Check if this is a valid match ID
        if ($match == null) {
            redirect($returnUrl, "ERROR", "Изисканият мач не съществува!");
        }
        // Check if the replay is part of the same problem
        if ($match->getProblemId() != $problem->getId()) {
            redirect($returnUrl, "ERROR", "Изисканият мач не е от тази задача!");
        }
        // Finally, check permissions
        if ($this->user->getAccess() < $GLOBALS["ACCESS_SEE_REPLAYS"]) {
            if ($this->user->getId() != $match->getUserOne() && $this->user->getId() != $match->getUserTwo()) {
                redirect($returnUrl, "ERROR", "Нямате права да видите този мач!");
            }
        }

        $replayLog = Grader::get_replay($match->getReplayKey());
        if (!$replayLog) {
            // Try running the match on the grader and getting the replay then
            if ($match->replay()) {
                // Poll for 30 seconds if the match has completed
                for ($iter = 0; $iter < 30; $iter++) {
                    sleep(1);
                    $match = Match::getById($matchId);
                    if ($match->getReplayKey() != "")
                        break;
                }
                $replayLog = Grader::get_replay($match->getReplayKey());
            }
            if (!$replayLog) {
                redirect($returnUrl, "ERROR", "Системата няма наличен лог за този мач!");
            }
        }

        $playerOne = User::getById($match->getUserOne());
        $playerOne = $playerOne != null ? $playerOne->getUsername() : sprintf("Author%d", -$match->getUserOne());
        $playerTwo = User::getById($match->getUserTwo());
        $playerTwo = $playerTwo != null ? $playerTwo->getUsername() : sprintf("Author%d", -$match->getUserTwo());
        $replayFunctionName = $this->getReplayFunction($_GET["game"]);

        return "
            <script>
                {$replayFunctionName}(`{$playerOne}`, `{$playerTwo}`, `{$replayLog}`);
            </script>
        ";
    }

    private function getOtherReplay(Problem $problem, int $submitId, int $testId): string {
        $returnUrl = getGameUrl($problem->getName()) . "/submits/{$submitId}";

        $submit = Submit::get($submitId);

        // Check if this is a valid match ID
        if ($submit == null) {
            redirect($returnUrl, "ERROR", "Изисканият събмит не съществува!");
        }
        // Check if the replay is part of the same problem
        if ($submit->getProblemId() != $problem->getId()) {
            redirect($returnUrl, "ERROR", "Изисканият събмит не е от тази задача!");
        }
        // Finally, check permissions
        if ($this->user->getAccess() < $GLOBALS["ACCESS_SEE_REPLAYS"]) {
            if ($this->user->getId() != $submit->getUserId()) {
                redirect($returnUrl, "ERROR", "Нямате права да видите този събмит!");
            }
        }

        $replayKey = "";
        $replayKeys = explode(",", $submit->getReplayKey());
        if (count($replayKeys) == count($submit->getResults())) {
            if ($testId >= 0 && $testId < count($replayKeys))
                $replayKey = $replayKeys[$testId];
        }
        $replayLog = $replayKey ? Grader::get_replay($replayKey) : "";

        // Try re-running the submit, so we get a replay log for it
        if (!$replayLog) {
            $submit->regrade();
            redirect($returnUrl, "ERROR", "Системата няма наличен лог за този събмит!");
        }

        $ranking = $this->getRelativeRanking($problem);
        $position = 1;
        for (; $position <= count($ranking); $position++)
            if ($ranking[$position - 1]["userId"] == $submit->getUserId())
                break;
        $suffix = ["st", "nd", "rd", "th", "th", "th", "th", "th", "th", "th"];
        $rank = "{$position}-{$suffix[($position - 1) % 10]}";
        $userName = $submit->getUserName() . " (ranked {$rank} out of " . count($ranking) . ")";
        $replayFunctionName = $this->getReplayFunction($_GET["game"]);

        return "
            <script>
                {$replayFunctionName}(`{$userName}`, `{$replayLog}`);
            </script>
        ";
    }

    private function getReplay(Problem $problem, int $submitId, int $matchId): string {
        if ($problem->getType() == "game") {
            return $this->getGameReplay($problem, $submitId, $matchId);
        } else {
            return $this->getOtherReplay($problem, $submitId, $matchId);
        }
    }

    // TODO: Make this part of the problem
    private function getUnofficial(Problem $problem): array {
        switch ($problem->getName()) {
            case "Snakes":
            case "Ultimate TTT":
            case "Connect":
            case "Tetris":
                return ["espr1t", "ThinkCreative"];
            case "HyperSnakes":
                return ["espr1t", "ThinkCreative", "IvayloS", "stuno", "ov32m1nd", "peterlevi"];
            case "HyperWords":
                return ["espr1t", "ThinkCreative", "IvayloS", "stuno"];
            case "ImageScanner":
                return ["espr1t", "ThinkCreative", "kopche", "emazing", "peterkazakov"];
            case "Airports":
                return ["espr1t", "kiv"];
        }
        return [];
    }

    private function getScoreboard(Problem $problem): string {
        $gameUrl = getGameUrl($problem->getName());
        if ($problem->getType() == "game") {
            $ranking = $this->getGameRanking($problem);
        } else {
            $ranking = $this->getRelativeRanking($problem);
        }
        $unofficial = $this->getUnofficial($problem);

        $scoreIsFloat = false;
        for ($i = 0; $i < count($ranking); $i += 1) {
            if (abs($ranking[$i]["score"] - round($ranking[$i]["score"]) > 0.001))
                $scoreIsFloat = true;
        }

        $rankingTable = "";
        for ($pos = 0; $pos < count($ranking); $pos++) {
            $user = User::getById($ranking[$pos]["userId"]);
            $submitId = $ranking[$pos]["submitId"];
            if ($user->getId() == $this->user->getId() || $this->user->getAccess() >= $GLOBALS["ACCESS_SEE_SUBMITS"]) {
                $submitId = "<a href='{$gameUrl}/submits/{$ranking[$pos]['submitId']}'>{$ranking[$pos]['submitId']}</a>";
            }

            $shownTitle = $scoreIsFloat ? sprintf("%.9f", $ranking[$pos]["score"]) : "";
            $shownScore = $scoreIsFloat ? sprintf("%.3f", $ranking[$pos]["score"]) : $ranking[$pos]["score"];

            $rankingTable .= "
                <tr>
                    <td>" . ($pos + 1) . "</td>
                    <td>" . getUserLink($user->getUsername(), $unofficial) . "</td>
                    <td>{$user->getName()}</td>
                    <td>{$submitId}</td>
                    <td title='{$shownTitle}'>{$shownScore}</td>
                </tr>
            ";
        }

        $content = "
            <h2><span class='blue'>{$problem->getName()}</span> :: Класиране</h2>
            <table class='default'>
                <tr>
                    <th>#</th>
                    <th>Потребител</th>
                    <th>Име</th>
                    <th>Събмит</th>
                    <th>Точки</th>
                </tr>
                {$rankingTable}
            </table>
            <div class='centered italic' style='font-size: smaller;'>
                Състезателите, отбелязани със звездичка (*), не участват в официалното класиране.
            </div>
        ";

        return "
            <script>
                showActionForm(`{$content}`, `{$gameUrl}`);
            </script>
        ";
    }

    // TODO: This may not be using the newest logic for match->log vs. match->replayKey
    //       Instead, we should call the back-end to get the actual replay using the replayKey
    private function getGameDemo(Problem $problem): string {
        $matches = Match::getGameMatches($problem->getId());
        $idx = rand() % count($matches);
        while ($matches[$idx]->getUserOne() < 0 || $matches[$idx]->getUserTwo() < 0 || $matches[$idx]->getReplayKey() == "")
            $idx = rand() % count($matches);

        $playerOne = User::getById($matches[$idx]->getUserOne());
        $playerTwo = User::getById($matches[$idx]->getUserTwo());
        $replayFunctionName = $this->getReplayFunction($_GET["game"]);
        $replay = "{$replayFunctionName}(`{$playerOne->getUsername()}`, `{$playerTwo->getUsername()}`, `{$matches[$idx]->getReplayKey()}`, true);";
        return $replay;
    }

    private function getInteractiveDemo(Problem $problem): string {
        $allSubmits = Submit::getAllSubmits(-1, $problem->getId(), $GLOBALS["STATUS_ACCEPTED"]);
        $users = array();
        $submits = array();
        for ($i = count($allSubmits) - 1; $i >= 0; $i--) {
            if ($allSubmits[$i]->getUserName() != "system") {
                if (!array_key_exists($allSubmits[$i]->getUserName(), $users)) {
                    $users[$allSubmits[$i]->getUserName()] = true;
                    array_push($submits, $allSubmits[$i]->getId());
                }
            }
        }

        // TODO: Make based on the number of tests the problem has.
        $testId = rand(0, 10);
        $submitId = $submits[rand(0, count($submits) - 1)];
        $replay = $this->getReplay($problem, $submitId, $testId);
        $replay = str_replace("<script>", "", $replay);
        $replay = str_replace("</script>", "", $replay);
        return $replay;
    }

    // Show the standings for a few seconds, then play a random replay
    private function getDemo(Problem $problem): string {
        $scoreboard = $this->getScoreboard($problem);
        $replay = $this->getInteractiveDemo($problem);
        // $replay = $this->getGameDemo($problem);

        $demoActions = "
            <script>
                setTimeout(function() {
                    {$replay}
                }, 5000);
            </script>
        ";
        return $scoreboard . $demoActions;
    }

    public function getContent(): string {
        $statusShortcut = false;
        if (isset($_SESSION["statusShortcut"])) {
            $statusShortcut = true;
            unset($_SESSION["statusShortcut"]);
        }

        if (isset($_GET["game"])) {
            $problem = Problem::getGameByName($_GET["game"]);
            if ($problem == null) {
                return $this->getMainPage();
            }
            if (!canSeeProblem($this->user, $problem->getVisible())) {
                redirect("/games", "ERROR", "Нямате права да видите тази игра.");
            }

            $content = "";
            if ($problem->getType() == "game") {
                $content = $this->getGameStatement($problem);
            } else if ($problem->getType() == "relative" || $problem->getType() == "interactive") {
                $content = $this->getRelativeStatement($problem);
            }
            if (isset($_GET["visualizer"])) {
                $visualizerFunctionNames = array(
                    "snakes" => "showSnakesVisualizer",
                    "hypersnakes" => "showHypersnakesVisualizer",
                    "ultimate-ttt" => "showUtttVisualizer",
                    "tetris" => "showTetrisVisualizer",
                    "connect" => "showConnectVisualizer"
                );
                // Adds "<script>showGameVisualizer("espr1t");</script>" block
                $content .= "<script>{$visualizerFunctionNames[$_GET["game"]]}(`{$this->user->getUsername()}`);</script>";
            } else if (isset($_GET["scoreboard"])) {
                $content .= $this->getScoreboard($problem);
            } else if (isset($_GET["submits"])) {
                if ($this->user->getId() == -1) {
                    redirect(getGameUrl($problem->getName()), "ERROR", "Трябва да влезете в профила си за да видите тази страница.");
                } else if (!isset($_GET["submitId"])) {
                    $content .= $this->getAllSubmitsBox($problem);
                } else {
                    if (isset($_GET["source"])) {
                        $this->getSource($problem, $_GET["submitId"]);
                    } else if (isset($_GET["updates"])) {
                        $this->getSubmitUpdates($problem, $_GET["submitId"]);
                    } else {
                        if ($statusShortcut)
                            $_SESSION["statusShortcut"] = true;
                        if (!isset($_GET["matchId"])) {
                            $content .= $this->getSubmitInfoBox($problem, $_GET["submitId"]);
                        } else {
                            $content .= $this->getReplay($problem, $_GET["submitId"], $_GET["matchId"]);
                        }
                    }
                }
            } else if (isset($_GET["demo"])) {
                $content = $this->getDemo($problem);
            } else if (isset($_GET["print"])) {
                $content = $this->getPrintStatement($problem);
            } else if (isset($_GET["pdf"])) {
                return_pdf_file($problem);
            }
            return $content;
        }
        return $this->getMainPage();
    }
}

?>
