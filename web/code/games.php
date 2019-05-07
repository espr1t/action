<?php
require_once('actions/print_pdf.php');
require_once('db/brain.php');
require_once('entities/problem.php');
require_once('entities/submit.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');
require_once('events.php');

class GamesPage extends Page {

    public function getTitle() {
        return 'O(N)::Games';
    }

    public function getExtraScripts() {
        return array(
            '/scripts/language_detector.js',
            '/scripts/games/snakes.js',
            '/scripts/games/uttt.js',
            '/scripts/games/hypersnakes.js',
            '/scripts/games/tetris.js',
            '/scripts/games/connect.js',
            '/scripts/jquery-3.3.1.min.js',
            '/scripts/jquery-jvectormap-2.0.3.min.js',
            '/scripts/jquery-jvectormap-world-mill.js'
        );
    }

    public function getExtraStyles() {
        return array('/styles/tooltips.css', '/styles/games.css', '/styles/jquery-jvectormap-2.0.3.css');
    }

    public function onLoad() {
        return 'addPreTags()';
    }

    public static function getGameRanking($problem) {
        // Returns a sorted array of data:
        // {
        //     'user': <integer>,
        //     'submit': <integer>,
        //     'score': <float>
        // }
        // The array is sorted from best to worst.
        // A user is ranked better than another user, if he/she has more points or has submitted earlier.

        $brain = new Brain();
        $matches = $brain->getGameMatches($problem->id);

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
                $submit[$userKey] = $playerScore[$userKey] = $opponentScore[$userKey] = 0;
            }
        }

        // Get the scores, wins, draws, and losses for each player
        $games = array();
        foreach ($matches as $match) {
            // If one of the users is negative, this means this is a partial submission match.
            if (intval($match['userOne']) < 0 || intval($match['userTwo']) < 0)
                continue;

            // Don't even get me started on why we must prepend with "User_"...
            $userOneKey = 'User_' . $match['userOne'];
            $userTwoKey = 'User_' . $match['userTwo'];

            $scoreUserOne = floatval($match['scoreOne']);
            $scoreUserTwo = floatval($match['scoreTwo']);

            // TODO: Fix this in a better way (add scoreWin and scoreTie to each game)
            if ($problem->name == "Ultimate TTT") {
                if ($scoreUserOne > $scoreUserTwo) $scoreUserOne = 3;
                if ($scoreUserTwo > $scoreUserOne) $scoreUserTwo = 3;
            }

            // Player one scores
            $submit[$userOneKey] = intval($match['submitOne']);
            $playerScore[$userOneKey] += $scoreUserOne;
            $opponentScore[$userOneKey] += $scoreUserTwo;

            // Player two scores
            $submit[$userTwoKey] = intval($match['submitTwo']);
            $playerScore[$userTwoKey] += $scoreUserTwo;
            $opponentScore[$userTwoKey] += $scoreUserOne;
        }

        $ranking = array();
        $numPlayers = count($playerScore);
        for ($pos = 0; $pos < $numPlayers; $pos++) {
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

            array_push($ranking, array(
                'user' => intval(substr($bestUser, 5)),
                'score' => $playerScore[$bestUser],
                'submit' => $submit[$bestUser]
            ));
            unset($playerScore[$bestUser]);
        }
        return $ranking;
    }

    private static function populateRelativePoints($problem, &$bestScores, &$userSubmits) {
        $brain = new Brain();
        $submits = $brain->getProblemSubmits($problem->id);
        // Take into account only the latest submission of each user
        $submits = array_reverse($submits);

        foreach ($submits as $submitDict) {
            $submit = Submit::instanceFromArray($submitDict, array('source' => ''));
            // Skip system submits
            if ($submit->userId == 0)
                continue;
            $userKey = 'User_' . $submit->userId;
            if (array_key_exists($userKey, $userSubmits)) {
                continue;
            }
            $userSubmits[$userKey] = $submit;

            for ($i = 0; $i < count($submit->results); $i += 1) {
                if (count($bestScores) <= $i) {
                    array_push($bestScores, 0.0);
                }
                if (is_numeric($submit->results[$i]) && $bestScores[$i] < floatval($submit->results[$i])) {
                    $bestScores[$i] = floatval($submit->results[$i]);
                }
            }
        }

        // Hack to make Airports use the grader's score
        // This, in fact, makes the task just a standard problem, but, oh well, I need it to be a game.
        if ($problem->name == 'Airports') {
            for ($i = 0; $i < count($bestScores); $i += 1)
                $bestScores[$i] = 1.0;
        }
    }

    public static function getRelativeRanking($problem) {
        // Returns a sorted array of data:
        // {
        //     'user': <integer>,
        //     'submit': <integer>,
        //     'score': <float>
        // }
        // The array is sorted from best to worst.
        // A user is ranked better than another user, if he/she has more points or has submitted earlier.

        $bestScores = array();
        $userSubmits = array();
        GamesPage::populateRelativePoints($problem, $bestScores, $userSubmits);

        $testScore = 100.0 / (count($bestScores) - 1);

        // TODO: Make the formula be configurable per problem
        $scoringPower = 1.0;
        if ($problem->name == 'HyperWords')
            $scoringPower = 2.0;

        $ranking = array();
        foreach ($userSubmits as $userKey => $submit) {
            $score = 0.0;
            for ($i = 1; $i < count($submit->results); $i += 1) {
                if (is_numeric($submit->results[$i])) {
                    if (floatval($submit->results[$i]) > 0.0) {
                        $score += pow($submit->results[$i] / $bestScores[$i], $scoringPower) * $testScore;
                    }
                }
            }
            array_push($ranking, array(
                'user' => intval(substr($userKey, 5)),
                'score' => $score,
                'submit' => $submit->id
            ));
        }

        usort($ranking, function($user1, $user2) {
            if ($user1['score'] != $user2['score']) {
                return $user1['score'] < $user2['score'] ? +1 : -1;
            } else {
                return $user1['submit'] < $user2['submit'] ? -1 : +1;
            }
        });
        return $ranking;
    }

    private function getAllGames() {
        $brain = new Brain();
        $games = $brain->getAllGames();
        // Show newest games first
        $games = array_reverse($games);

        $gameList = '';
        // Calculate statistics (position and points) for each game for this user
        foreach ($games as $game) {
            $problem = Problem::instanceFromArray($game);

            // Don't show hidden games
            if (!canSeeProblem($this->user, $problem->visible, $problem->id))
                continue;

            $ranking = array();
            if ($problem->type == 'game') {
                $ranking = $this->getGameRanking($problem);
            } else {
                $ranking = $this->getRelativeRanking($problem);
            }

            $position = 0;
            for (; $position < count($ranking); $position += 1)
                if ($ranking[$position]['user'] == $this->user->id)
                    break;
            $scoreStr = $positionStr = 'N/A';
            if ($position < count($ranking)) {
                $scoreStr = sprintf("%.2f (best is %.2f)",
                    $ranking[$position]['score'], $ranking[0]['score']);
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
                        <div class="game-image"><img class="game-image" src="' . $game['logo'] . '"></div>
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

            $gameList .= $gameBox;
        }
        return $gameList;
    }

    private function getMainPage() {
        $text = '
            <h1>Игри</h1>
            Тук можете да намерите няколко игри, за които трябва да напишете изкуствен интелект.
        ';
        $header = inBox($text);
        $gamesList = $this->getAllGames();
        return $header . $gamesList;
    }

    private function getGameStatement($problem) {
        $statementFile = sprintf('%s/%s/%s',
            $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
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
                    <a href="' . getGameUrl($problem->name) . '/visualizer">
                        <input type="submit" value="Визуализатор" class="button button-color-blue button-large" title="Визуализатор на играта">
                    </a>
        ';
        $scoreboardButton = '
                    <br>
                    <a href="' . getGameUrl($problem->name) . '/scoreboard">
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
                    <a style="font-size: smaller;" href="' . getGameUrl($problem->name) . '/submits">Предадени решения</a>
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

        $visibilityRestriction = $problem->visible ? '' : '<i class="fa fa-eye-slash" title="This problem is hidden."></i>';
        return '
            <div class="box' . ($GLOBALS['user']->id == -1 ? '' : ' box-problem') . '">
                <div class="problem-visibility">' . $visibilityRestriction . '</div>
                <div class="problem-title" id="problem-title">' . $problem->name . '</div>
                <div class="problem-origin">' . $problem->origin . '</div>
                <div class="problem-resources"><b>Time Limit:</b> ' . $problem->timeLimit . 's, <b>Memory Limit:</b> ' . $problem->memoryLimit . 'MiB</div>
                <div class="separator"></div>
                <div class="problem-statement">' . $statement . '</div>
                ' . $controlButtons . '
            </div>
            <div class="problem-stats-links">
                <a href="' . getGameUrl($problem->name) . '/pdf" style="color: #333333;" target="_blank"><div class="tooltip--top" data-tooltip="PDF" style="display: inline-block;"><i class="fas fa-file-pdf"></i></div></a>
            </div>
        ';
    }

    private function getRelativeStatement($problem) {
        $statementFile = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
        $statement = file_get_contents($statementFile);

        $submitFormContent = '
            <h2><span class="blue">' . $problem->name . '</span> :: Изпращане на Решение</h2>
            <div class="center">Решението ще получи пропорционални точки спрямо авторското решение, или това на най-добрия друг участник.</div>
            <br>
            <div class="center">
                <textarea name="source" class="submit-source" cols=80 rows=24 id="source"></textarea>
            </div>
            <div class="italic right" style="font-size: 0.8em;">Detected language: <span id="language">?</span></div>
            <div class="center"><input type="submit" value="Изпрати" onclick="submitSubmitForm(' . $problem->id . ');" class="button button-color-red"></div>
        ';

        $submitButton = '';
        $seeSubmissionsLink = '';
        $visualizerButton = '
                    <a href="' . getGameUrl($problem->name) . '/visualizer">
                        <input type="submit" value="Визуализатор" class="button button-color-blue button-large">
                    </a>
        ';
        $scoreboardButton = '
                    <br>
                    <a href="' . getGameUrl($problem->name) . '/scoreboard">
                        <input type="submit" value="Класиране" class="button button-color-blue button-small" title="Класиране на участниците.">
                    </a>
        ';

        if ($this->user->access >= $GLOBALS['ACCESS_SUBMIT_SOLUTION']) {
            $remainPartial = 0;
            $remainFull = 0;
            getWaitingTimes($this->user, $problem, $remainPartial, $remainFull);

            // Submit button
            if ($remainFull <= 0) {
                $submitButton = '
                        <script>function showFullForm() {showSubmitForm(`' . $submitFormContent . '`);}</script>
                        <input type="submit" onclick="showFullForm();" value="Изпрати Решение" class="button button-large button-color-blue">
                ';
            } else {
                $submitButton = '
                        <input type="submit" value="Изпрати Решение" class="button button-large button-color-gray"
                                title="Ще можете да предадете отново след ' . $remainFull . ' секунди.">
                ';
            }

            // See previous submissions link
            $seeSubmissionsLink = '
                    <br>
                    <a style="font-size: smaller;" href="' . getGameUrl($problem->name) . '/submits">Предадени решения</a>
            ';
        } else {
            $submitButton = '
                <input type="submit" value="Изпрати Решение" class="button button-large button-color-gray"
                        title="Трябва да влезете в системата за да можете да предавате решения.">
            ';
        }

        if ($problem->name == 'HyperWords' || $problem->name == 'Airports') {
            $visualizerButton = '';
        }

        $controlButtons = '
                <div class="center">
                    ' . $submitButton . '
                    ' . $visualizerButton . '
                    ' . $seeSubmissionsLink . '
                    ' . $scoreboardButton . '
                </div>
        ';

        $visibilityRestriction = $problem->visible ? '' : '<i class="fa fa-eye-slash" title="This problem is hidden."></i>';
        return '
            <div class="box' . ($GLOBALS['user']->id == -1 ? '' : ' box-problem') . '">
                <div class="problem-visibility">' . $visibilityRestriction . '</div>
                <div class="problem-title" id="problem-title">' . $problem->name . '</div>
                <div class="problem-origin">' . $problem->origin . '</div>
                <div class="problem-resources"><b>Time Limit:</b> ' . $problem->timeLimit . 's, <b>Memory Limit:</b> ' . $problem->memoryLimit . 'MiB</div>
                <div class="separator"></div>
                <div class="problem-statement">' . $statement . '</div>
                ' . $controlButtons . '
            </div>
            <div class="problem-stats-links">
                <a href="' . getGameUrl($problem->name) . '/pdf" style="color: #333333;" target="_blank"><div class="tooltip--top" data-tooltip="PDF" style="display: inline-block;"><i class="fas fa-file-pdf"></i></div></a>
            </div>
        ';
    }

    private function getPrintStatement($problem) {
        $statementFile = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
        $statement = file_get_contents($statementFile);
        return '
            <div class="problem-title" id="problem-title">' . $problem->name . '</div>
            <div class="problem-origin">' . $problem->origin . '</div>
            <div class="problem-resources"><b>Time Limit:</b> ' . $problem->timeLimit . 's, <b>Memory Limit:</b> ' . $problem->memoryLimit . 'MiB</div>
            <div class="separator"></div>
            <div class="problem-statement">' . $statement . '</div>
        ';
    }

    private function getGameStatusTable($submit, $status, $found, $totalScoreUser, $totalScoreOpponents) {
        $color = getStatusColor($status);
        // An old submit, no score for it
        if ($color == 'green' && $found == false)
            $color = 'gray';

        if ($found) {
            $score = sprintf('<span>%.0f:%.0f</span>', $totalScoreUser, $totalScoreOpponents);
        } else {
            $score = sprintf('<span title="Точки се изчисляват само за последното изпратено решение.">-</span>');
        }

        return '
            <table class="default ' . $color . '">
                <tr>
                    <th>Статус на задачата</th>
                    <th style="width: 100px;">Време</th>
                    <th style="width: 100px;">Памет</th>
                    <th style="width: 100px;">Резултат</th>
                </tr>
                <tr>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$status] . '</td>
                    <td>' . sprintf("%.2fs", max($submit->exec_time)) . '</td>
                    <td>' . sprintf("%.2f MiB", max($submit->exec_memory)) . '</td>
                    <td>' . $score . '</td>
                </tr>
            </table>
        ';
    }

    private function getGameDetailsTable($problem, $submit, $status, $found, $games, $matchesPerGame) {
        // If compilation error, pretty-print it and return instead of the per-match circles
        if ($status == $GLOBALS['STATUS_COMPILATION_ERROR']) {
            return prettyPrintCompilationErrors($submit);
        }

        // If there is no information about this submission, then there is a newer submitted solution
        // Let the user know that only the latest submissions are being kept as official
        if (!$found) {
            return '
                <div class="centered italic">
                    Имате по-ново предадено решение.<br>
                    Точки се изчисляват само за последното изпратено такова.
                </div>
            ';
        }

        $matchColumns = '';
        for ($i = 1; $i <= $matchesPerGame; $i += 1) {
            $matchColumns .= '
                    <th style="width: 5%;">' . $i . '</th>
            ';
        }
        $detailsTable = '
            <table class="default blue">
                <tr>
                    <th style="width: 28%;">Опонент</th>
                    <th style="width: 12%;">Резултат</th>'
                    . $matchColumns . '
                </tr>
        ';

        ksort($games);
        foreach($games as $opponentKey => $results) {
            $opponentId = intval(explode('_', $opponentKey)[1]);
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
                    $classes = 'far fa-check-circle green';
                } else if ($result['scoreUser'] < $result['scoreOpponent']) {
                    // Loss
                    $classes = 'far fa-times-circle red';
                } else {
                    if ($result['scoreUser'] == 0 && $result['scoreOpponent'] == 0) {
                        // Not played
                        $classes = 'far fa-question-circle gray';
                        $message = 'Мачът още не е изигран.';
                        $showLink = false;
                    } else {
                        // Draw
                        $classes = 'far fa-pause-circle yellow';
                    }
                }
                $perTestStatus .= '
                    <td>
                        ' . ($showLink ? '<a href="' . getGameUrl($problem->name) . '/submits/' . $submit->id . '/replays/' . $result['id'] .'">' : '') . '
                            <i class="' . $classes . '" title="' . $message . '"></i>
                        ' . ($showLink ? '</a>' : '') . '
                    </td>
                ';
            }
            $detailsTable .= '
                <tr>
                    <td>' . $opponentName . '</td>
                    <td>' . sprintf('%.0f:%.0f', $scoreUser, $scoreOpponent) . '</td>
                    ' . $perTestStatus . '
                </tr>
            ';
        }

        $detailsTable .= '
            </table>
        ';
        return $detailsTable;
    }

    private function getGameSubmitInfoBoxContent($problem, $submitId, $redirectUrl) {
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        $status = $submit->calcStatus();

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

            // TODO: Fix this in a better way (add scoreWin and scoreTie to each game)
            if ($problem->name == "Ultimate TTT") {
                if ($scoreUser > $scoreOpponent) $scoreUser = 3;
                if ($scoreOpponent > $scoreUser) $scoreOpponent = 3;
            }

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

        $statusTable = $this->getGameStatusTable($submit, $status, $found, $totalScoreUser, $totalScoreOpponents);
        $detailsTable = $this->getGameDetailsTable($problem, $submit, $status, $found, $games, $matchesPerGame);

        $author = '';
        if ($this->user->id != $submit->userId) {
            $author = '(' . $submit->userName . ')';
        }

        $source = getSourceSection($submit);

        return '
            <h2><span class="blue">' . $problem->name . '</span> :: Статус на решение ' . $author . '</h2>
            <div class="right" style="font-size: smaller">' . explode(' ', $submit->submitted)[0] . ' | ' . explode(' ', $submit->submitted)[1] . '</div>
            <br>
            ' . $statusTable . '
            <br>
            ' . $detailsTable . '
            <br>
            ' . $source . '
        ';
    }

    private function getRelativeStatusTable($submit, $points) {
        $color = getStatusColor($submit->status);
        return '
            <table class="default ' . $color . '">
                <tr>
                    <th>Статус на задачата</th>
                    <th style="width: 100px;">Време</th>
                    <th style="width: 100px;">Памет</th>
                    <th style="width: 100px;">Точки</th>
                </tr>
                <tr>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$submit->status] . '</td>
                    <td>' . sprintf("%.2fs", max($submit->exec_time)) . '</td>
                    <td>' . sprintf("%.2f MiB", max($submit->exec_memory)) . '</td>
                    <td>' . sprintf("%.3f", array_sum($points) - $points[0]) . '</td>
                </tr>
            </table>
        ';
    }

    private function getRelativeDetailsTable($submit, $points) {
        // If compilation error, pretty-print it and return instead of the per-test circles
        if ($submit->status == $GLOBALS['STATUS_COMPILATION_ERROR']) {
            return prettyPrintCompilationErrors($submit);
        }

        if ($submit->problemName == 'Airports') {
            if ($submit->status == $GLOBALS['STATUS_ACCEPTED']) {
                return '<div id="world-map" style="width: 43rem; height: 20rem;"></div>';
            }
        }

        // Otherwise get information for each test and print it as a colored circle with
        // additional roll-over information
        $detailsTable = '<div class="centered">';
        for ($i = 1; $i < count($points); $i = $i + 1) {
            if ($i > 1 && $i % 10 == 1) {
                $detailsTable .= '<br>';
            }
            $result = $submit->results[$i];
            $tooltip =
                'Тест ' . $i . PHP_EOL .
                'Статус: ' . (is_numeric($result) ? 'OK' : $result) . PHP_EOL .
                'Точки: ' . sprintf('%.1f', $points[$i]) . ' (' . $result . ')' . PHP_EOL .
                'Време: ' . sprintf('%.2fs', $submit->exec_time[$i]) . PHP_EOL .
                'Памет: ' . sprintf('%.2f MiB', $submit->exec_memory[$i])
            ;

            $icon = 'WTF?';
            $background = '';
            if (is_numeric($result)) {
                $maxPoints = 100.0 / (count($points) - 1);
                $background = (abs($points[$i] - $maxPoints) < 0.001 ? 'dull-green' : 'dull-teal');
                $icon = '<i class="fa fa-check"></i>';
            } else if ($result == $GLOBALS['STATUS_WAITING'] || $result == $GLOBALS['STATUS_PREPARING'] || $result == $GLOBALS['STATUS_COMPILING']) {
                $background = 'dull-gray';
                $icon = '<i class="fas fa-hourglass-start"></i>';
            } else if ($result == $GLOBALS['STATUS_TESTING']) {
                $background = 'dull-gray';
                $icon = '<i class="fa fa-spinner fa-pulse"></i>';
            } else if ($result == $GLOBALS['STATUS_WRONG_ANSWER']) {
                $background = 'dull-red';
                $icon = '<i class="fa fa-times"></i>';
            } else if ($result == $GLOBALS['STATUS_TIME_LIMIT']) {
                $background = 'dull-red';
                $icon = '<i class="fa fa-clock"></i>';
            } else if ($result == $GLOBALS['STATUS_MEMORY_LIMIT']) {
                $background = 'dull-red';
                $icon = '<i class="fa fa-database"></i>';
            } else if ($result == $GLOBALS['STATUS_RUNTIME_ERROR']) {
                $background = 'dull-red';
                $icon = '<i class="fa fa-bug"></i>';
            } else if ($result == $GLOBALS['STATUS_COMPILATION_ERROR']) {
                $background = 'dull-red';
                $icon = '<i class="fa fa-code"></i>';
            } else if ($result == $GLOBALS['STATUS_INTERNAL_ERROR']) {
                $background = 'dull-black';
                $icon = '<i class="fas fa-exclamation"></i>';
            }
            $detailsTable .= '<div class="test-result tooltip--top background-' . $background . '" data-tooltip="' . $tooltip . '">' . $icon . '</div>';
        }
        $detailsTable .= '</div>';
        $detailsTable = '<div class="test-result-wrapper">' . $detailsTable . '</div>';

        return $detailsTable;
    }

    function getRelativeSubmitInfoBoxContent($problem, $submitId, $redirectUrl) {
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);

        $bestScores = array();
        $userSubmits = array();
        $this->populateRelativePoints($problem, $bestScores, $userSubmits);

        // TODO: Make the formula to be configurable per problem
        $scoringPower = 1.0;
        if ($problem->name == 'HyperWords')
            $scoringPower = 2.0;

        $points = array();
        $testWeight = 100.0 / (count($bestScores) - 1);
        for ($i = 0; $i < count($bestScores); $i += 1) {
            if (!is_numeric($submit->results[$i]) || $submit->results[$i] == 0.0) {
                array_push($points, 0.0);
            } else {
                $score = $submit->results[$i] >= $bestScores[$i] ? 1.0 : pow($submit->results[$i] / $bestScores[$i], $scoringPower);
                array_push($points, $score * $testWeight);
            }
        }

        $statusTable = $this->getRelativeStatusTable($submit, $points);
        $detailsTable = $this->getRelativeDetailsTable($submit, $points);

        $author = '';
        if ($this->user->id != $submit->userId) {
            $author = '(' . $submit->userName . ')';
        }

        $source = getSourceSection($submit, false);

        return '
            <h2><span class="blue">' . $problem->name . '</span> :: Статус на решение ' . $author . '</h2>
            <div class="right" style="font-size: smaller">' . explode(' ', $submit->submitted)[0] . ' | ' . explode(' ', $submit->submitted)[1] . '</div>
            <br>
            ' . $statusTable . '
            <br>
            ' . $detailsTable . '
            <br>
            ' . $source . '
        ';
    }

    private function getSubmitUpdates($problem, $submitId) {
        $UPDATE_DELAY = 500000; // 0.5s (in microseconds)
        $MAX_UPDATES = 180 * 1000000 / $UPDATE_DELAY; // 180 seconds

        $lastContent = '';
        for ($updateId = 0; $updateId < $MAX_UPDATES; $updateId++) {
            $content = '';
            if ($problem->type == 'game') {
                $content = $this->getGameSubmitInfoBoxContent($problem, $submitId, '');
            } else if ($problem->type == 'relative') {
                $content = $this->getRelativeSubmitInfoBoxContent($problem, $submitId, '');
            }

            if (strcmp($content, $lastContent) != 0) {
                sendServerEventData('content', $content);
                $lastContent = $content;
            }
            // If nothing to wait for, stop the updates
            $allTested = strpos($content, 'fa-hourglass-start') === false &&
                         strpos($content, 'fa-spinner') === false &&
                         strpos($content, 'fa-question-circle') == false;
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

    private function getAirportsScript($problem, $submitId, $redirectUrl) {
        $airportData = explode("\n", trim(file_get_contents($GLOBALS['PATH_PROBLEMS'] . '/Games.Airports/Misc/AirportData.csv')));
        $airportInfo = array();
        for ($i = 1; $i < count($airportData); $i += 1) {
            $line = explode(',', trim($airportData[$i]));
            $airportInfo[$line[0]] = array($line[1], $line[2]);
        }

        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        $content = '';
        $airports = explode(',', trim($submit->info));
        $content .= 'var data = [';
        foreach ($airports as $airport) {
            $content .= '{name: "' . $airport . '", latLng:[' . $airportInfo[$airport][0] . ',' . $airportInfo[$airport][1] . ']},';
        }
        $content .= '];' . PHP_EOL;
        $content .= '      $(function() {
            $("#world-map").vectorMap({
                map: "world_mill",
                backgroundColor: "white",
                zoomMin: 1,
                zoomMax: 1,
                regionStyle: {
                    initial: {
                        fill: "#9A9A9A",
                        "fill-opacity": 1,
                        stroke: "none",
                        "stroke-width": 0,
                        "stroke-opacity": 1
                    },
                    hover: {
                        "fill-opacity": 0.8,
                        cursor: "pointer"
                    },
                },
                markerStyle: {
                    initial: {
                        fill: "#129D5A",
                        stroke: "#505050",
                        "fill-opacity": 1,
                        "stroke-width": 1,
                        "stroke-opacity": 1,
                        r: 5
                    },
                    hover: {
                        stroke: "#333333",
                        "stroke-width": 2,
                        cursor: "pointer"
                    }
                },
                markers: data
            });
        });';
        return $content;
    }

    private function getSubmitInfoBox($problem, $submitId) {
        $redirectUrl = getGameUrl($problem->name) . '/submits';
        if (isset($_SESSION['queueShortcut']))
            $redirectUrl = '/queue';

        $content = '';
        if ($problem->type == 'game') {
            $content = $this->getGameSubmitInfoBoxContent($problem, $submitId, $redirectUrl);
            return '
                <script>
                    showActionForm(`' . $content . '`, \'' . $redirectUrl . '\');
                </script>
            ';
        } else if ($problem->type == 'relative') {
            $content = $this->getRelativeSubmitInfoBoxContent($problem, $submitId, $redirectUrl);
            if ($problem->name != 'Airports') {
                $updatesUrl = getGameUrl($problem->name) . '/submits/' . $submitId . '/updates';
                return '
                    <script>
                        showActionForm(`' . $content . '`, \'' . $redirectUrl . '\');
                        subscribeForUpdates(\'' . $updatesUrl . '\');
                    </script>
                ';
            } else {
                return '
                    <script>
                        showActionForm(`' . $content . '`, \'' . $redirectUrl . '\');
                        ' . $this->getAirportsScript($problem, $submitId, $redirectUrl) . '
                    </script>
                ';
            }
        } else {
            error_log("ERROR: In games, but problem is neither 'game' nor 'relative'!");
            return '';
        }
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
            $submitLink = '<a href="' . getGameUrl($problem->name) . '/submits/' . $submit->id . '">' . $submit->id . '</a>';
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

        $returnUrl = getGameUrl($problem->name);

        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $returnUrl . '\');
            </script>
        ';
    }

    private function getReplayFunction($gameName) {
        if ($gameName == 'snakes') return 'showSnakesReplay';
        if ($gameName == 'ultimate-ttt') return 'showUtttReplay';
        if ($gameName == 'hypersnakes') return 'showHypersnakesReplay';
        if ($gameName == 'connect') return 'showConnectReplay';
        return 'undefinedFunction';
    }

    private function getReplay($problem, $submitId, $matchId) {
        $returnUrl = getGameUrl($problem->name) . '/submits/' . $submitId;

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

        $functionName = $this->getReplayFunction($_GET['game']);
        $content = '
            <script>
                ' . $functionName . '("'. $playerOne . '", "' . $playerTwo .'", "' . $match->log . '");
            </script>
        ';

        return $content;
    }

    private function getUnofficial($problem) {
        switch ($problem->name) {
            case 'Snakes':
                return array('espr1t', 'ThinkCreative');
            case 'Ultimate TTT':
                return array('espr1t', 'ThinkCreative');
            case 'HyperSnakes':
                return array('espr1t', 'ThinkCreative', 'IvayloS', 'stuno', 'ov32m1nd', 'peterlevi');
            case 'Connect':
                return array('espr1t', 'ThinkCreative');
            case 'Tetris':
                return array('espr1t', 'ThinkCreative');
            case 'HyperWords':
                return array('espr1t', 'ThinkCreative', 'IvayloS', 'stuno');
            case 'Airports':
                return array('espr1t', 'kiv');
        }
        return array();
    }

    private function getScoreboard($problem) {
        $ranking = array();
        if ($problem->type == 'game') {
            $ranking = $this->getGameRanking($problem);
        } else {
            $ranking = $this->getRelativeRanking($problem);
        }
        $unofficial = $this->getUnofficial($problem);

        $scoreIsFloat = false;
        for ($i = 0; $i < count($ranking); $i += 1) {
            if (abs($ranking[$i]['score'] - round($ranking[$i]['score']) > 0.001))
                $scoreIsFloat = true;
        }

        $rankingTable = '';
        for ($pos = 0; $pos < count($ranking); $pos += 1) {
            $user = User::get($ranking[$pos]['user']);
            $submitId = $ranking[$pos]['submit'];
            if ($user->id == $this->user->id || $this->user->access >= $GLOBALS['ACCESS_SEE_SUBMITS']) {
                $submitId = '<a href="' . getGameUrl($problem->name) . '/submits/' . $ranking[$pos]['submit'] . '">' . $ranking[$pos]['submit'] . '</a>';
            }

            $shownTitle = $scoreIsFloat ? sprintf('%.9f', $ranking[$pos]['score']) : '';
            $shownScore = $scoreIsFloat ? sprintf('%.3f', $ranking[$pos]['score']) : $ranking[$pos]['score'];

            $rankingTable .= '
                <tr>
                    <td>' . ($pos + 1) . '</td>
                    <td>' . getUserLink($user->username, $unofficial) . '</td>
                    <td>' . $user->name . '</td>
                    <td>' . $submitId . '</td>
                    <td title="' . $shownTitle . '">' . $shownScore . '</td>
                </tr>
            ';
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
                ' . $rankingTable . '
            </table>
            <div class="centered italic" style="font-size: smaller;">
                Състезателите, отбелязани със звездичка (*), не участват в официалното класиране.
            </div>
        ';

        $returnUrl = getGameUrl($problem->name);
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
        $functionName = $this->getReplayFunction($_GET['game']);
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
            $problem = getGameByName($_GET['game']);
            if ($problem == null) {
                return $this->getMainPage();
            }
            if (!canSeeProblem($this->user, $problem->visible, $problem->id)) {
                redirect('/games', 'ERROR', 'Нямате права да видите тази игра.');
            }

            if ($problem->type == 'game') {
                $content = $this->getGameStatement($problem);
            } else if ($problem->type == 'relative') {
                $content = $this->getRelativeStatement($problem);
            }
            if (isset($_GET['visualizer'])) {
                if ($_GET['game'] == 'snakes') {
                    $content .= '<script>showSnakesVisualizer("'. $this->user->username . '");</script>';
                } else if ($_GET['game'] == 'hypersnakes') {
                    $content .= '<script>showHypersnakesVisualizer("'. $this->user->username . '");</script>';
                } else if ($_GET['game'] == 'ultimate-ttt') {
                    $content .= '<script>showUtttVisualizer("'. $this->user->username . '");</script>';
                } else if ($_GET['game'] == 'tetris') {
                    $content .= '<script>showTetrisVisualizer("'. $this->user->username . '");</script>';
                } else if ($_GET['game'] == 'connect') {
                    $content .= '<script>showConnectVisualizer("'. $this->user->username . '");</script>';
                }
            } else if (isset($_GET['scoreboard'])) {
                $content .= $this->getScoreboard($problem);
            } else if (isset($_GET['submits'])) {
                if ($this->user->id == -1) {
                    redirect(getGameUrl($problem->name), 'ERROR', 'Трябва да влезете в профила си за да видите тази страница.');
                } else if (!isset($_GET['submitId'])) {
                    $content .= $this->getAllSubmitsBox($problem);
                } else {
                    if (isset($_GET['updates'])) {
                        $this->getSubmitUpdates($problem, $_GET['submitId']);
                    } else {
                        if ($queueShortcut)
                            $_SESSION['queueShortcut'] = true;
                        if (!isset($_GET['matchId'])) {
                            $content .= $this->getSubmitInfoBox($problem, $_GET['submitId']);
                        } else {
                            $content .= $this->getReplay($problem, $_GET['submitId'], $_GET['matchId']);
                        }
                    }
                }
            } else if (isset($_GET['demo'])) {
                $content = $this->getDemo($problem);
            } else if (isset($_GET['print'])) {
                $content = $this->getPrintStatement($problem);
            } else if (isset($_GET['pdf'])) {
                return_pdf_file($problem);
            }
            return $content;
        }
        return $this->getMainPage();
    }
}

?>
