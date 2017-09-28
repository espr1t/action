<?php
require_once('db/brain.php');
require_once('entities/problem.php');
require_once('entities/submit.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');
require_once('problems.php');

class GamesPage extends Page {

    public function getTitle() {
        return 'O(N)::Games';
    }

    public function getExtraScripts() {
        return array('/scripts/language_detector.js', '/scripts/snakes.js', '/scripts/uttt.js', '/scripts/hypersnakes.js');
    }

    public function getExtraStyles() {
        return array('/styles/games.css');
    }

    private function getGameByName($name) {
        $brain = new Brain();
        $gamesInfo = $brain->getAllGames();
        foreach ($gamesInfo as $gameInfo) {
            if (getGameUrlName($gameInfo['name']) == $name)
                return Problem::get($gameInfo['id']);
        }
        return null;
    }

    public function getGameRanking($gameId) {
        $brain = new Brain();
        $gameMatches = $brain->getGameMatches($gameId, 'all');

        $userPoints = array();
        $userSubmit = array();
        foreach ($gameMatches as $match) {
            // Partial submission, skip it
            if (intval($match['userOne']) < 0 || intval($match['userTwo']) < 0)
                continue;

            // An actual match, update scores
            for ($player = 1; $player <= 2; $player += 1) {
                // Don't even get me started on why we must prepend with "User_"...
                $user = 'User_' . $match[$player == 1 ? 'userOne' : 'userTwo'];
                $score = floatval($match[$player == 1 ? 'scoreOne' : 'scoreTwo']);
                $submit = intval($match[$player == 1 ? 'submitOne' : 'submitTwo']);
                if (!array_key_exists($user, $userPoints))
                    $userPoints[$user] = 0.0;
                $userPoints[$user] += $score;
                $userSubmit[$user] = $submit;
            }
        }
        arsort($userPoints);
        $ranking = array();
        foreach ($userPoints as $user => $points) {
            array_push($ranking, array(
                'user' => intval(substr($user, 5)),
                'points' => $points,
                'submit' => $userSubmit[$user]
            ));
        }
        return $ranking;
    }

    private function getAllGames() {
        $brain = new Brain();
        $games = $brain->getAllGames();

        $problems = '';
        // Calculate statistics (position and points) for each game for this user
        foreach ($games as $game) {
            // Don't show hidden games
            if (!canSeeProblem($this->user, $game['visible'] == '1', $game['id']))
                continue;

            $ranking = $this->getGameRanking($game['id']);

            $position = 0;
            for (; $position < count($ranking); $position += 1)
                if ($ranking[$position]['user'] == $this->user->id)
                    break;
            $scoreStr = $positionStr = 'N/A';
            if ($position < count($ranking)) {
                $scoreStr = sprintf("%.2f (best is %.2f)", $ranking[$position]['points'], $ranking[0]['points']);
                $positionStr = sprintf("%d (out of %d)", $position + 1, count($ranking));
            }

            $stats = '
                <i class="fa fa-trophy"></i> Position: ' . $positionStr . '
                &nbsp;&nbsp;
                <i class="fa fa-star"></i> Score: ' . $scoreStr . '
            ';

            $gameBox = '
                <a href="/games/' . getGameUrlName($game['name']) . '" class="decorated">
                    <div class="box narrow boxlink">
                        <div class="game-info">
                            <div class="game-name">' . $game['name'] . '</div>
                            <div class="game-stats">' . $stats . '</div>
                            <div class="game-description">' . $game['description'] . '</div>
                        </div>
                        <div class="game-image"><img src="' . $game['logo'] . '"></div>
                    </div>
                </a>
            ';
            // Make hidden games grayed out (actually visible only to admins).
            if ($game['visible'] == '0') {
                $gameBox = '
                    <div style="opacity: 0.5;">
                    ' . $gameBox . '
                    </div>
                ';
            }

            $problems .= $gameBox;
        }
        return $problems;
    }

    private function getMainPage() {
        $text = '<h1>Игри</h1>
                 Тук можете да намерите няколко игри, за които можете да напишете изкуствен интелект.
        ';
        $header = inBox($text);
        $gamesList = $this->getAllGames();
        return $header . $gamesList;
    }

    private function getStatement($problem) {
        $statementFile = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
        $statement = file_get_contents($statementFile);

        $partialSubmitInfo = "Частичното решение се тества срещу няколко авторски решения с различна сложност и не се запазва като финално.";
        if ($problem->waitPartial > 0) {
            $partialSubmitInfo .= "
Можете да предавате такова решение веднъж на всеки " . $problem->waitPartial . " минути.";
        }
        $fullSubmitInfo = "Пълното решение се тества срещу всички решения и се запазва като финално (дори да сте предали по-добро по-рано).";
        if ($problem->waitFull > 0) {
            $fullSubmitInfo .= "
Можете да предавате такова решение веднъж на всеки " . $problem->waitFull . " минути.";
        }

        $submitFormContent = '
            <h2><span class="blue">' . $problem->name . '</span> :: %s</h2>
            <div class="center">%s</div>
            <br>
            <div class="center">
                <textarea name="source" class="submit-source" cols=80 rows=24 id="source"></textarea>
            </div>
            <div class="italic right" style="font-size: 0.8em;">Detected language: <span id="language">?</span></div>
            <div class="center"><input type="submit" value="Изпрати" onclick="submitSubmitForm(' . $problem->id . ', %s);" class="button button-color-red"></div>
        ';
        $partialSubmitFormContent = sprintf($submitFormContent, 'Частично Решение', $partialSubmitInfo, 'false');
        $fullSubmitFormContent = sprintf($submitFormContent, 'Пълно Решение', $fullSubmitInfo, 'true');

        $partSubmitButton = '';
        $fullSubmitButton = '';
        $seeSubmissionsLink = '';
        $visualizerButton = '
                    <a href="' . getGameLink($problem->name) . '/visualizer">
                        <input type="submit" value="Визуализатор" class="button button-color-blue button-large" title="Визуализатор на играта">
                    </a>
        ';
        $scoreboardButton = '
                    <br>
                    <a href="' . getGameLink($problem->name) . '/scoreboard">
                        <input type="submit" value="Класиране" class="button button-color-blue button-small" title="Класиране на всички участници">
                    </a>
        ';

        if ($this->user->access >= $GLOBALS['ACCESS_SUBMIT_SOLUTION']) {
            $remainPartial = 0;
            $remainFull = 0;
            getWaitingTimes($this->user, $problem, $remainPartial, $remainFull);

            // Partial submit button
            if ($remainPartial <= 0) {
                $partSubmitButton = '
                        <script>function showPartialForm() {showSubmitForm(`' . $partialSubmitFormContent . '`);}</script>
                        <input type="submit" onclick="showPartialForm();" value="Частично решение" class="button button-large button-color-blue"
                                title="' . $partialSubmitInfo . '">
                ';
            } else {
                $partSubmitButton = '
                        <input type="submit" value="Частично решение" class="button button-large button-color-gray"
                                title="Ще можете да предадете отново след ' . $remainPartial . ' секунди.">
                ';
            }
            // Full submit button
            if ($remainFull <= 0) {
                $fullSubmitButton = '
                        <script>function showFullForm() {showSubmitForm(`' . $fullSubmitFormContent . '`);}</script>
                        <input type="submit" onclick="showFullForm();" value="Пълно решение" class="button button-large button-color-blue"
                                title="' . $fullSubmitInfo . '">
                ';
            } else {
                $fullSubmitButton = '
                        <input type="submit" value="Пълно решение" class="button button-large button-color-gray"
                                title="Ще можете да предадете отново след ' . $remainFull . ' секунди.">
                ';
            }
            // See previous submissions link
            $seeSubmissionsLink = '
                    <br>
                    <a style="font-size: smaller;" href="' . getGameLink($problem->name) . '/submits">Предадени решения</a>
            ';
        } else {
            $partSubmitButton = '
                <input type="submit" value="Частично решение" class="button button-large button-color-gray"
                        title="Трябва да влезете в системата за да можете да предавате решения.">
            ';
            $fullSubmitButton = '
                <input type="submit" value="Пълно решение" class="button button-large button-color-gray"
                        title="Трябва да влезете в системата за да можете да предавате решения.">
            ';
        }

        $controlButtons = '
                <div class="center">
                    ' . $partSubmitButton . '
                    ' . $visualizerButton . '
                    ' . $fullSubmitButton . '
                    ' . $seeSubmissionsLink . '
                    ' . $scoreboardButton . '
                </div>
        ';

        return '
            <div class="box' . ($GLOBALS['user']->id == -1 ? '' : ' box-problem') . '">
                <div class="problem-title" id="problem-title">' . $problem->name . '</div>
                <div class="problem-origin">' . $problem->origin . '</div>
                <div class="problem-resources"><b>Time Limit:</b> ' . $problem->timeLimit . 's, <b>Memory Limit:</b> ' . $problem->memoryLimit . 'MiB</div>
                <div class="separator"></div>
                <div class="problem-statement">' . $statement . '</div>
                ' . $controlButtons . '
            </div>
        ';
    }

    private function getSubmitInfoBox($problem, $submitId) {
        $returnUrl = getGameLink($problem->name) . '/submits';
        if (isset($_SESSION['queueShortcut']))
            $returnUrl = '/queue';

        if (!is_numeric($submitId)) {
            redirect($returnUrl, 'ERROR', 'Не съществува решение с този идентификатор!');
        }

        $submit = Submit::get($submitId);
        if ($submit == null) {
            redirect($returnUrl, 'ERROR', 'Не съществува решение с този идентификатор!');
        }

        if ($this->user->access < $GLOBALS['ACCESS_SEE_SUBMITS']) {
            if ($submit->userId != $this->user->id) {
                redirect($returnUrl, 'ERROR', 'Нямате достъп до това решение!');
            }

            if ($submit->problemId != $problem->id) {
                redirect($returnUrl, 'ERROR', 'Решението не е по поисканата задача!');
            }
        }

        $brain = new Brain();
        $matches = $brain->getGameMatches($problem->id, $submit->userId);

        $matchesPerGame = count($submit->results) * 2;

        $found = false;
        $games = array();
        $totalScoreUser = 0;
        $totalScoreOpponents = 0;
        foreach ($matches as $match) {
            $opponentId = $submit->userId == intval($match['userOne']) ? intval($match['userTwo']) : intval($match['userOne']);
            // If submit is full we care only about matches against actual users
            // If submit is partial we care only about matches against author's solutions
            if (($submit->full && $opponentId < 0) || (!$submit->full && $opponentId > 0))
                continue;

            $lastSubmit = $submit->userId == intval($match['userOne']) ? intval($match['submitOne']) : intval($match['submitTwo']);
            // If not latest submit, skip it
            if ($submit->id != $lastSubmit)
                continue;

            $found = true;
            $opponentKey = 'User_' . $opponentId;
            if (!array_key_exists($opponentKey, $games))
                $games[$opponentKey] = array();

            $scoreUser = $submit->userId == intval($match['userOne']) ? floatval($match['scoreOne']) : floatval($match['scoreTwo']);
            $scoreOpponent = $submit->userId == intval($match['userOne']) ? floatval($match['scoreTwo']) : floatval($match['scoreOne']);

            if (intval($match['test']) >= 0) {
                $totalScoreUser += $scoreUser;
                $totalScoreOpponents += $scoreOpponent;
                array_push($games[$opponentKey], array(
                    'id' => $match['id'],
                    'scoreUser' => $scoreUser,
                    'scoreOpponent' => $scoreOpponent,
                    'message' => $match['message'],
                    'log' => $match['log']
                ));
            }
        }

        $summaryTable = '';
        $detailedTable = '';

        $status = $submit->calcStatus();
        $color = 'black';
        switch ($status) {
            case $GLOBALS['STATUS_ACCEPTED']:
                $color = 'green';
                break;
            case $GLOBALS['STATUS_INTERNAL_ERROR']:
                $color = 'black';
                break;
            default:
                $color = strlen($status) == 1 ? 'gray' : 'red';
        }
        // An old submit, no score for it
        if ($color == 'green' && $found == false)
            $color = 'gray';
        $problemStatus = $GLOBALS['STATUS_DISPLAY_NAME'][$status];

        if ($found) {
            $score = sprintf('<span>%.0f:%.0f</span>', $totalScoreUser, $totalScoreOpponents);
            $infoMessage = '';
        } else {
            $score = sprintf('<span title="Точки се изчисляват само за последното изпратено решение.">-</span>');
            $infoMessage = '<div class="centered italic">
                                Имате по-ново предадено решение.<br>
                                Точки се изчисляват само за последното изпратено такова.
                            </div>';
        }

        $summaryTable = '
            <table class="default ' . $color . '">
                <tr>
                    <th>Статус на задачата</th>
                    <th style="width: 100px;">Време</th>
                    <th style="width: 100px;">Памет</th>
                    <th style="width: 100px;">Резултат</th>
                </tr>
                <tr>
                    <td>' . $problemStatus . '</td>
                    <td>' . sprintf("%.2fs", max($submit->exec_time)) . '</td>
                    <td>' . sprintf("%.2f MiB", max($submit->exec_memory)) . '</td>
                    <td>' . $score . '</td>
                </tr>
            </table>
        ';

        $detailedTable = '';
        if ($found) {
            $matchColumns = '';
            for ($i = 1; $i <= $matchesPerGame; $i += 1) {
                $matchColumns .= '
                        <th style="width: 5%;">' . $i . '</th>
                ';
            }
            $detailedTable = '
                <table class="default blue">
                    <tr>
                        <th style="width: 28%;">Опонент</th>
                        <th style="width: 12%;">Резултат</th>'
                        . $matchColumns . '
                    </tr>
            ';

            ksort($games);
            foreach($games as $opponentKey => $results) {
                $opponentId = intval(split('_', $opponentKey)[1]);
                $opponentName = '';
                if ($opponentId < 0) {
                    $opponentName = sprintf('Author%d', -$opponentId);
                } else {
                    $opponent = User::get($opponentId);
                    $opponentName = getUserLink($opponent->username);
                }

                $scoreUser = 0;
                $scoreOpponent = 0;
                $perTestStatus = '';
                foreach ($results as $result) {
                    $scoreUser += $result['scoreUser'];
                    $scoreOpponent += $result['scoreOpponent'];
                    $message = $result['message'];
                    $log = $result['log'];
                    $showLink = true;

                    if ($result['scoreUser'] > $result['scoreOpponent']) {
                        // Win
                        $classes = 'fa fa-check-circle-o green';
                    } else if ($result['scoreUser'] < $result['scoreOpponent']) {
                        // Loss
                        $classes = 'fa fa-times-circle-o red';
                    } else {
                        if ($result['scoreUser'] == 0 && $result['scoreOpponent'] == 0) {
                            // Not played
                            $classes = 'fa fa-question-circle-o gray';
                            $message = 'Мачът още не е изигран.';
                            $showLink = false;
                        } else {
                            // Draw
                            $classes = 'fa fa-pause-circle-o yellow';
                        }
                    }
                    $perTestStatus .= '
                        <td>
                            ' . ($showLink ? '<a href="' . getGameLink($problem->name) . '/submits/' . $submit->id . '/replays/' . $result['id'] .'">' : '') . '
                                <i class="' . $classes . '" title="' . $message . '"></i>
                            ' . ($showLink ? '</a>' : '') . '
                        </td>
                    ';
                }
                $detailedTable .= '
                    <tr>
                        <td>' . $opponentName . '</td>
                        <td>' . sprintf('%.0f:%.0f', $scoreUser, $scoreOpponent) . '</td>
                        ' . $perTestStatus . '
                    </tr>
                ';
            }

            $detailedTable .= '
                </table>
            ';
        }

        // If compilation error, pretty-print it so the user has an idea what is happening
        $message = '';
        if ($problemStatus == $GLOBALS['STATUS_DISPLAY_NAME'][$GLOBALS['STATUS_COMPILATION_ERROR']]) {
            $message = prettyPrintCompilationErrors($submit);
        }

        // Don't display the per-test circles if compilation error, display the error instead
        if ($message != '') {
            $detailedTable = $message;
        }

        $author = '';
        if ($this->user->id != $submit->userId) {
            $author = '(' . $submit->userName . ')';
        }

        $source = '
            <div class="centered" id="sourceLink">
                <a onclick="displaySource();">Виж кода</a>
            </div>
            <div style="display: none;" id="sourceField">
                <div class="right smaller"><a onclick="copyToClipboard();">копирай</a></div>
                <div class="show-source-box">
                    <code id="source">' . htmlspecialchars(addslashes($submit->source)) . '</code>
                </div>
            </div>
        ';

        $content = '
            <h2><span class="blue">' . $problem->name . '</span> :: Статус на решение ' . $author . '</h2>
            <div class="right smaller">' . explode(' ', $submit->submitted)[0] . ' | ' . explode(' ', $submit->submitted)[1] . '</div>
            <br>
            ' . $summaryTable . '
            <br>
            ' . $infoMessage . '
            <br>
            ' . $detailedTable . '
            <br>
            ' . $source . '
        ';

        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $returnUrl . '\');
            </script>
        ';
    }

    private function getAllSubmitsBox($problem) {
        $submits = Submit::getUserSubmits($this->user->id, $problem->id);

        $finalFull = -1;
        foreach ($submits as $submit) {
            if ($submit->full && $submit->id > $finalFull)
                $finalFull = $submit->id;
        }

        $submitList = '';
        for ($i = 0; $i < count($submits); $i = $i + 1) {
            $submit = $submits[$i];
            $submitLink = '<a href="' . getGameLink($problem->name) . '/submits/' . $submit->id . '">' . $submit->id . '</a>';
            $submitList .= '
                <tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . explode(' ', $submit->submitted)[0] . '</td>
                    <td>' . explode(' ', $submit->submitted)[1] . '</td>
                    <td>' . $submitLink . '</td>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$submit->calcStatus()] . '</td>
                    <td>' . ($finalFull == $submit->id ? '<i class="fa fa-check green"></i>' : '') . '</td>
                </tr>
            ';
        }

        $content = '
            <h2><span class="blue">' . $problem->name . '</span> :: Ваши решения</h2>
            <table class="default">
                <tr>
                    <th>#</th>
                    <th>Дата</th>
                    <th>Час</th>
                    <th>Идентификатор</th>
                    <th>Статус</th>
                    <th>Финално</th>
                </tr>
                ' . $submitList . '
            </table>
        ';

        $returnUrl = getGameLink($problem->name);

        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $returnUrl . '\');
            </script>
        ';
    }

    private function getReplay($problem, $submitId, $matchId) {
        $returnUrl = getGameLink($problem->name) . '/submits/' . $submitId;

        $match = Match::getById($matchId);

        // Check if this is a valid match ID
        if ($match == null) {
            redirect($returnUrl, 'ERROR', 'Изисканият мач не съществува!');
        }
        // Check if the replay is part of the same problem
        if ($match->problemId != $problem->id) {
            redirect($returnUrl, 'ERROR', 'Изисканият мач не е от тази задача!');
        }
        // Finally, check permissions
        if ($this->user->access < $GLOBALS['ACCESS_SEE_REPLAYS']) {
            if ($this->user->id != $match->userOne && $this->user->id != $match->userTwo) {
                redirect($returnUrl, 'ERROR', 'Нямате права да видите този мач!');
            }
        }

        $playerOne = User::get($match->userOne);
        $playerOne = $playerOne != null ? $playerOne->username : sprintf('Author%d', -$match->userOne);
        $playerTwo = User::get($match->userTwo);
        $playerTwo = $playerTwo != null ? $playerTwo->username : sprintf('Author%d', -$match->userTwo);

        $functionName = $_GET['game'] == 'snakes' ? 'showSnakesReplay' :
                        $_GET['game'] == 'ultimate-ttt' ? 'showUtttReplay' : 'showHyperSnakesReplay';
        $content = '
            <script>
                ' . $functionName . '("'. $playerOne . '", "' . $playerTwo .'", "' . $match->log . '");
            </script>
        ';

        return $content;
    }

    private function getScoreboard($problem) {
        $returnUrl = getGameLink($problem->name);

        $brain = new Brain();
        $matches = $brain->getGameMatches($problem->id);

        $wins = array();
        $draws = array();
        $losses = array();
        $submit = array();
        $playerScore = array();
        $opponentScore = array();

        // Initialize all arrays with zeroes
        foreach ($matches as $match) {
            // If one of the users is negative, this means this is a partial submission match.
            if (intval($match['userOne']) < 0 || intval($match['userTwo']) < 0)
                continue;
            for ($player = 0; $player < 2; $player += 1) {
                $userKey = ($player == 0 ? 'User_' . $match['userOne'] : 'User_' . $match['userTwo']);
                $wins[$userKey] = $draws[$userKey] = $losses[$userKey] = 0;
                $submit[$userKey] = $playerScore[$userKey] = $opponentScore[$userKey] = 0;
            }
        }

        // Get the scores, wins, draws, and losses for each player
        $games = array();
        foreach ($matches as $match) {
            // If one of the users is negative, this means this is a partial submission match.
            if (intval($match['userOne']) < 0 || intval($match['userTwo']) < 0)
                continue;

            $userOneKey = 'User_' . $match['userOne'];
            $userTwoKey = 'User_' . $match['userTwo'];

            // Player one scores
            $submit[$userOneKey] = intval($match['submitOne']);
            $playerScore[$userOneKey] += floatval($match['scoreOne']);
            $opponentScore[$userOneKey] += floatval($match['scoreTwo']);

            // Player two scores
            $submit[$userTwoKey] = intval($match['submitTwo']);
            $playerScore[$userTwoKey] += floatval($match['scoreTwo']);
            $opponentScore[$userTwoKey] += floatval($match['scoreOne']);

            // Wins and losses
            if ($match['scoreOne'] > $match['scoreTwo']) {
                $wins[$userOneKey] += 1;
                $losses[$userTwoKey] += 1;
            } else if ($match['scoreOne'] == $match['scoreTwo']) {
                $draws[$userOneKey] += 1;
                $draws[$userTwoKey] += 1;
            } else {
                $losses[$userOneKey] += 1;
                $wins[$userTwoKey] += 1;
            }
        }

        $numPlayers = count($playerScore);
        $unofficial = array();
        if ($problem->name == 'HyperSnakes') {
            $unofficial = array('espr1t', 'IvayloS', 'stuno');
        } else if ($problem->name == 'Ultimate TTT') {
            $unofficial = array('espr1t');
        }

        $ranking = '';
        for ($pos = 1; $pos <= $numPlayers; $pos++) {
            $bestUser = '';
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

            $user = User::get(intval(split('_', $bestUser)[1]));
            $submitId = $submit[$bestUser];
            if ($bestUser == 'User_' . $this->user->id ||
                    $this->user->access > $GLOBALS['ACCESS_SEE_SUBMITS']) {
                $submitId = '<a href="' . getGameLink($problem->name) . '/submits/' . $submit[$bestUser] . '">' . $submit[$bestUser] . '</a>';
            }
            $title = '' . $wins[$bestUser] . '/' . $draws[$bestUser] . '/' . $losses[$bestUser];
            $ranking .= '
                <tr>
                    <td>' . $pos . '</td>
                    <td>' . getUserLink($user->username, $unofficial) . '</td>
                    <td>' . $user->name . '</td>
                    <td>' . $submitId . '</td>
                    <td title="' . $title . '">' . $maxPlayerScore . '</td>
                </tr>
            ';

            unset($playerScore[$bestUser]);
        }

        $content = '
            <h2><span class="blue">' . $problem->name . '</span> :: Класиране</h2>
            <table class="default">
                <tr>
                    <th>#</th>
                    <th>Потребител</th>
                    <th>Име</th>
                    <th>Събмит</th>
                    <th>Точки</th>
                </tr>
                ' . $ranking . '
            </table>
            <div class="centered italic" style="font-size: smaller;">
                Състезателите, отбелязани със звездичка (*), не участват в официалното класиране.
            </div>
        ';

        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $returnUrl . '\');
            </script>
        ';
    }

    private function getDemo($problem) {
        // Show the standings for 5 seconds, then play a random replay

        $brain = new Brain();
        $matches = $brain->getGameMatches($problem->id);
        $idx = rand() % count($matches);
        while ($matches[$idx]['userOne'] < 0 || $matches[$idx]['userTwo'] < 0 || $matches[$idx]['log'] == '')
            $idx = rand() % count($matches);

        $scoreboard = $this->getScoreboard($problem);

        $playerOne = User::get($matches[$idx]['userOne']);
        $playerTwo = User::get($matches[$idx]['userTwo']);
        $functionName = $_GET['game'] == 'snakes' ? 'showSnakesReplay' :
                        $_GET['game'] == 'ultimate-ttt' ? 'showUtttReplay' : 'showHyperSnakesReplay';
        $replay = $functionName . '("'. $playerOne->username . '", "' . $playerTwo->username .'", "' . $matches[$idx]['log'] . '", true);';

        $demoActions = '
            <script>
                setTimeout(function() {hideActionForm();}, 4500);
                setTimeout(function() {' . $replay . '}, 5000);
            </script>
        ';
        return $scoreboard . $demoActions;
    }

    public function getContent() {
        $queueShortcut = false;
        if (isset($_SESSION['queueShortcut'])) {
            $queueShortcut = true;
            unset($_SESSION['queueShortcut']);
        }

        if (isset($_GET['game'])) {
            $problem = $this->getGameByName($_GET['game']);
            if ($problem == null || $_GET['game'] == 'snakes') {
                return $this->getMainPage();
            }

            $content = $this->getStatement($problem);
            if (isset($_GET['visualizer'])) {
                if ($_GET['game'] == 'snakes') {
                    $content .= '<script>showSnakesVisualizer("'. $this->user->username . '");</script>';
                } else if ($_GET['game'] == 'hypersnakes') {
                    $content .= '<script>showHyperSnakesVisualizer("'. $this->user->username . '");</script>';
                } else if ($_GET['game'] == 'ultimate-ttt') {
                    $content .= '<script>showUtttVisualizer("'. $this->user->username . '");</script>';
                }
            } else if (isset($_GET['scoreboard'])) {
                $content .= $this->getScoreboard($problem);
            } else if (isset($_GET['submits'])) {
                if ($this->user->id == -1) {
                    redirect(getGameLink($problem->name), 'ERROR', 'Трябва да влезете в профила си за да видите тази страница.');
                } else if (!isset($_GET['submitId'])) {
                    $content .= $this->getAllSubmitsBox($problem);
                } else {
                    if ($queueShortcut)
                        $_SESSION['queueShortcut'] = true;
                    if (!isset($_GET['matchId'])) {
                        $content .= $this->getSubmitInfoBox($problem, $_GET['submitId']);
                    } else {
                        $content .= $this->getReplay($problem, $_GET['submitId'], $_GET['matchId']);
                    }
                }
            } else if (isset($_GET['demo']) && $this->user->username == 'ThinkCreative') {
                $content = $this->getDemo($problem);
            }
            return $content;
        }
        return $this->getMainPage();
    }
}

?>
