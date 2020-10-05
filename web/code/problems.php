<?php
require_once('actions/print_pdf.php');
require_once('db/brain.php');
require_once('entities/problem.php');
require_once('entities/submit.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');
require_once('events.php');


class ProblemsPage extends Page {
    private $problemName = null;

    public function getTitle() {
        if ($this->problemName == null) {
            return 'O(N)::Problems';
        }
        return 'O(N)::' . $this->problemName;
    }

    public function getExtraScripts() {
        return array('/scripts/language_detector.js');
    }

    public function getExtraStyles() {
        return array('/styles/tooltips.css');
    }

    public function onLoad() {
        return 'addPreTags()';
    }

    public function getProblemBox($problemInfo, $haveTried, $haveSolved) {
        $statusTooltip = 'Още не сте пробвали да решите тази задача.';
        $statusIconClass = 'fal fa-circle gray';
        if ($haveTried) {
            $statusTooltip = 'Пробвали сте неуспешно тази задача.';
            $statusIconClass = 'fas fa-times-circle red';
        }
        if ($haveSolved) {
            $statusTooltip = 'Вече сте решили успешно тази задача.';
            $statusIconClass = 'fas fa-check-circle green';
        }
        $status = '
            <div class="tooltip--right" data-tooltip="' . $statusTooltip . '">
                <i class="' . $statusIconClass . '"></i>
            </div>
        ';

        $difficultyTooltip = 'Unknown';
        $difficultyIconClass = 'fas fa-question';
        switch ($problemInfo['difficulty']) {
            case 'trivial':
                $difficultyTooltip = 'Много лесна';
                $difficultyIconClass = 'fad fa-duck';
                // $difficultyIconClass = 'far fa-grin-tongue-wink';
                break;
            case 'easy':
                $difficultyTooltip = 'Лесна';
                $difficultyIconClass = 'fas fa-feather-alt';
                // $difficultyIconClass = 'far fa-grin-beam';
                break;
            case 'medium':
                $difficultyTooltip = 'Средна';
                $difficultyIconClass = 'fas fa-brain';
                // $difficultyIconClass = 'far fa-flushed';
                break;
            case 'hard':
                $difficultyTooltip = 'Трудна';
                $difficultyIconClass = 'fas fa-paw-claws';
                // $difficultyIconClass = 'far fa-frown';
                break;
            case 'brutal':
                $difficultyTooltip = 'Много трудна';
                $difficultyIconClass = 'fas fa-biohazard';
                // $difficultyIconClass = 'far fa-angry';
                break;
        }
        $difficulty = '
            <div class="tooltip--top" data-tooltip="' . $difficultyTooltip . '">
                <i class="' . $difficultyIconClass . '"></i>
            </div>
        ';

        $solutionsTooltip = 'Решена от ' . $problemInfo['solutions'] . ' човек' . ($problemInfo['solutions'] != 1 ? 'a' : '');
        $solutions = '
            <div class="tooltip--top" data-tooltip="' . $solutionsTooltip . '">
                <span style="font-weight: bold;">' . $problemInfo['solutions'] . ' <i class="fas fa-users fa-sm"></i></span>
            </div>
        ';

        $successTooltip = '' . $problemInfo['successRate'] . '% успеваемост';
        $success = '
            <div class="tooltip--top" data-tooltip="' . $successTooltip . '">
                <span style="font-weight: bold;">' . $problemInfo['successRate'] . ' <i class="fas fa-percentage fa-sm"></i></span>
            </div>
        ';

        $box = '
            <a href="/problems/' . $problemInfo['id'] . '" class="decorated">
                <div class="box narrow boxlink">
                    <div class="problem-status">' . $status . '</div>
                    <div class="problem-name">' . $problemInfo['name'] . '</div>
                    <div class="problem-stats">' . $difficulty . ' | ' . $solutions . ' | ' . $success . '</div>
                </div>
            </a>
        ';
        // Make hidden problems grayed out (actually visible only to admins).
        if ($problemInfo['visible'] == '0') {
            $box = '<div style="opacity: 0.5;">' . $box . '</div>';
        }
        return $box;
    }

    // TODO: Make this function faster by pre-calculating problem stats
    public function getProblems($problemList=null) {
        $allProblems = Brain::getAllProblems();
        $problemsInfo = array();
        foreach ($allProblems as $problem) {
            if ($problemList == null || in_array($problem['id'], $problemList)) {
                array_push($problemsInfo, $problem);
            }
        }

        $totalSubmits = array();
        $successfulSubmits = array();
        foreach ($problemsInfo as $problem) {
            $totalSubmits[$problem['id']] = 0;
            $successfulSubmits[$problem['id']] = 0;
        }

        foreach (Brain::getProblemStatusCounts() as $statusCnt) {
            if (array_key_exists($statusCnt['problemId'], $totalSubmits)) {
                $totalSubmits[$statusCnt['problemId']] += $statusCnt['count'];
                if ($statusCnt['status'] == $GLOBALS['STATUS_ACCEPTED'])
                    $successfulSubmits[$statusCnt['problemId']] += $statusCnt['count'];
            }
        }

        // Calculate number of solutions and success rate
        for ($i = 0; $i < count($problemsInfo); $i++) {
            $problemsInfo[$i]['solutions'] = $successfulSubmits[$problemsInfo[$i]['id']];
            $problemsInfo[$i]['successRate'] = $totalSubmits[$problemsInfo[$i]['id']] == 0 ? 0 :
                round(100 * $successfulSubmits[$problemsInfo[$i]['id']] / $totalSubmits[$problemsInfo[$i]['id']]);
        }

        $problemTried = array();
        $problemSolved = array();
        foreach (Submit::getUserSubmits($this->user->id) as $submit) {
            $problemTried[$submit->problemId] = true;
            if ($submit->status == $GLOBALS['STATUS_ACCEPTED'])
                $problemSolved[$submit->problemId] = true;
        }

        for ($i = 0; $i < count($problemsInfo); $i++) {
            $problemsInfo[$i]['box'] = $this->getProblemBox($problemsInfo[$i],
                                                            isset($problemTried[$problemsInfo[$i]['id']]),
                                                            isset($problemSolved[$problemsInfo[$i]['id']]));
        }

        // Order by solutions or difficulty, if requested
        if (isset($_GET['order'])) {
            $priority = ['solutions', 'difficulty', 'successRate'];
            if ($_GET['order'] == 'solutions') {
                $priority = ['solutions', 'difficulty', 'successRate'];
            } else if ($_GET['order'] == 'difficulty') {
                $priority = ['difficulty', 'solutions', 'successRate'];
            } else if ($_GET['order'] == 'success') {
                $priority = ['successRate', 'solutions', 'difficulty'];
            }
            $numericDifficulty = array('trivial' => 0, 'easy' => 1, 'medium' => 2, 'hard' => 3, 'brutal' => 4);
            usort($problemsInfo, function($left, $right) use($numericDifficulty, $priority) {
                for ($i = 0; $i < count($priority); $i++) {
                    $lValue = $priority[$i] == 'difficulty' ? -$numericDifficulty[$left[$priority[$i]]] : $left[$priority[$i]];
                    $rValue = $priority[$i] == 'difficulty' ? -$numericDifficulty[$right[$priority[$i]]] : $right[$priority[$i]];
                    if ($lValue != $rValue)
                        return $rValue - $lValue;
                }
                // If everything else is the same, order by ID
                return $left['id'] - $right['id'];
            });
        }

        $problems = '';
        foreach ($problemsInfo as $problemInfo) {
            // Don't show hidden problems
            if (!canSeeProblem($this->user, $problemInfo['visible'] == '1'))
                continue;
            $problems .= $problemInfo['box'];
        }
        return $problems;
    }

    private function getOrderings() {
        $orderByDifficulty = '<a href="?order=difficulty">сложност</a>';
        $orderBySolutions = '<a href="?order=solutions">брой решения</a>';
        $orderBySuccessRate = '<a href="?order=success">успеваемост</a>';
        return '
            <div class="right" style="font-size: smaller;">
                Подреди по: ' . $orderByDifficulty . ' | ' . $orderBySolutions . ' | ' . $orderBySuccessRate . '
            </div>
        ';
    }

    private function getMainPage() {
        $text = '<h1>Задачи</h1>
                 Тук можете да намерите списък с всички задачи от тренировката.
        ';
        $header = inBox($text);
        $orderings = $this->getOrderings();
        $problems = $this->getProblems();
        return $header . $orderings . $problems;
    }

    private function getStatsBox($problem) {
        $submits = Brain::getProblemSubmits($problem->id);

        $bestTime = 1e10;
        $bestMemory = 1e10;
        $acceptedSubmits = 0;
        $allSubmits = 0;
        foreach ($submits as $submit) {
            $allSubmits += 1;
            if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                $acceptedSubmits += 1;
                $time = array_map(function ($el) {return floatval($el);}, explode(',', $submit['execTime']));
                $memory = array_map(function ($el) {return floatval($el);}, explode(',', $submit['execMemory']));
                $bestTime = min([$bestTime, max($time)]);
                $bestMemory = min([$bestMemory, max($memory)]);
            }
        }
        $successRate = 100.0 * ($acceptedSubmits == 0 ? 0.0 : $acceptedSubmits / $allSubmits);

        $content = '
            <h2><span class="blue">' . $problem->name . '</span> :: Статистики</h2>
            <div class="centered">
                <div class="problem-stats-field">
                    <div class="problem-stats-field-circle">' . ($acceptedSubmits == 0 ? '-' : sprintf("%.2fs", $bestTime)) . '</div>
                    <div class="problem-stats-field-label">време</div>
                </div>
                <div class="problem-stats-field">
                    <div class="problem-stats-field-circle">' . ($acceptedSubmits == 0 ? '-' : sprintf("%.2fMB", $bestMemory)) . '</div>
                    <div class="problem-stats-field-label">памет</div>
                </div>
                <div class="problem-stats-field">
                    <div class="problem-stats-field-circle">' . $GLOBALS['PROBLEM_DIFFICULTIES'][$problem->difficulty] . '</div>
                    <div class="problem-stats-field-label">сложност</div>
                </div>
                <div class="problem-stats-field">
                    <div class="problem-stats-field-circle">' . $acceptedSubmits . '</div>
                    <div class="problem-stats-field-label">решения</div>
                </div>
                <div class="problem-stats-field">
                    <div class="problem-stats-field-circle">' . sprintf("%.2f", $successRate) . '%</div>
                    <div class="problem-stats-field-label">успеваемост</div>
                </div>
            </div>
        ';

        $redirect = '/problems/' . $problem->id;
        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $redirect . '\');
            </script>
        ';
    }

    private function getUsersBox($problem) {
        $submits = Brain::getProblemSubmits($problem->id, 'AC');

        $usersBest = array();
        foreach ($submits as $submit) {
            // Skip system user
            if ($submit['userId'] < 1)
                continue;

            if (!array_key_exists($submit['userId'], $usersBest)) {
                // If first AC for this user on this problem add it to the list
                $usersBest[$submit['userId']] = $submit;
            } else {
                // Otherwise, see if this submission is better than the current best
                $current = $usersBest[$submit['userId']];
                $curWorstTime = max(array_map(function ($el) {return floatval($el);}, explode(',', $current['execTime'])));
                $curWorstMemory = max(array_map(function ($el) {return floatval($el);}, explode(',', $current['execMemory'])));
                $newWorstTime = max(array_map(function ($el) {return floatval($el);}, explode(',', $submit['execTime'])));
                $newWorstMemory = max(array_map(function ($el) {return floatval($el);}, explode(',', $submit['execMemory'])));
                if ($newWorstTime < $curWorstTime || ($newWorstTime == $curWorstTime && $newWorstMemory < $curWorstMemory)) {
                    $usersBest[$submit['userId']] = $submit;
                }
            }
        }

        $tableContent = '';
        foreach ($usersBest as $user => $submit) {
            $maxTime = max(array_map(function ($el) {return floatval($el);}, explode(',', $submit['execTime'])));
            $maxMemory = max(array_map(function ($el) {return floatval($el);}, explode(',', $submit['execMemory'])));
            $tableContent .= '
                <tr>
                    <td>' . getUserLink($submit['userName']) . '</td>
                    <td>' . $submit['language'] . '</td>
                    <td>' . sprintf("%.2f", $maxTime) . '</td>
                    <td>' . sprintf("%.2f", $maxMemory) . '</td>
                    <td>' . $submit['submitted'] . '</td>
                </tr>
            ';
        }

        $content = '
            <h2><span class="blue">' . $problem->name . '</span> :: Решения</h2>
            <div class="centered">
                <table class="default">
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
                        ' . $tableContent . '
                    </tbody>
                </table>
            </div>
        ';

        $redirect = '/problems/' . $problem->id;
        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $redirect . '\');
            </script>
        ';
    }

    private function getTagsBox($problem) {
        $tags = '';
        foreach ($problem->tags as $tag) {
            $tags .= ($tags == '' ? '' : ' | ') . $GLOBALS['PROBLEM_TAGS'][$tag];
        }
        $content = '
            <h2><span class="blue">' . $problem->name . '</span> :: Тагове</h2>
            <div class="centered">' . $tags . '</div>
        ';

        $redirect = '/problems/' . $problem->id;
        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $redirect . '\');
            </script>
        ';
    }

    private function getStatement($problem) {
        $statementFile = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
        $statement = file_get_contents($statementFile);

        $submitFormContent = '
            <h2><span class="blue">' . $problem->name . '</span> :: Предаване на Решение</h2>
            <div class="center">
                <textarea name="source" class="submit-source" cols=80 rows=24 maxlength=50000 id="source"></textarea>
            </div>
            <div class="italic right" style="font-size: 0.8em;">Detected language: <span id="language">?</span></div>
            <div class="center"><input type="submit" value="Изпрати" onclick="submitSubmitForm(' . $problem->id . ');" class="button button-color-red"></div>
        ';

        $submitButtons = '';
        if ($this->user->access >= $GLOBALS['ACCESS_SUBMIT_SOLUTION']) {
            $submitButtons = '
                <script>
                    function showForm() {
                        showSubmitForm(`' . $submitFormContent . '`);
                    }
                </script>
                <div class="center">
                    <input type="submit" value="Предай решение" onclick="showForm();" class="button button-color-blue button-large">
                    <br>
                    <a style="font-size: smaller;" href="/problems/' . $problem->id . '/submits">Предадени решения</a>
                </div>
            ';
        } else {
            $submitButtons = '
                <div class="center">
                    <input type="submit" value="Предай решение" class="button button-color-gray button-large" title="Трябва да влезете в системата за да можете да предавате решения.">
                </div>
            ';
        }

        $visibilityRestriction = $problem->visible ? '' : '<div class="tooltip--bottom" data-tooltip="Задачата е скрита."><i class="fa fa-eye-slash"></i></div>';
        $statsButton = '<a href="/problems/' . $problem->id . '/stats" style="color: #333333;"><div class="tooltip--top" data-tooltip="информация"><i class="fa fa-info-circle"></i></div></a>';
        $usersButton = '<a href="/problems/' . $problem->id . '/users" style="color: #333333;"><div class="tooltip--top" data-tooltip="потребители"><i class="fa fa-users"></i></div></a>';
        $tagsButton = '<a href="/problems/' . $problem->id . '/tags" style="color: #333333;"><div class="tooltip--top" data-tooltip="тагове"><i class="fa fa-tags"></i></div></a>';
        $pdfButton = '<a href="/problems/' . $problem->id . '/pdf" style="color: #333333;" target="_blank"><div class="tooltip--top" data-tooltip="PDF"><i class="fas fa-file-pdf"></i></div></a>';
        // Remove PDF link for logged-out users (bots tend to click on it and generate PDFs for all problems)
        if ($this->user->access < $GLOBALS['ACCESS_DOWNLOAD_AS_PDF']) {
            $pdfButton = '<div class="tooltip--top" data-tooltip="PDF" title="Трябва да влезете в системата за да изтеглите условието като PDF."><i class="fas fa-file-pdf" style="opacity: 0.5;"></i></div>';
        }
        return '
            <div class="box box-problem">
                <div class="problem-visibility">' . $visibilityRestriction . '</div>
                <div class="problem-title" id="problem-title">' . $problem->name . '</div>
                <div class="problem-origin">' . $problem->origin . '</div>
                <div class="problem-resources"><b>Time Limit:</b> ' . $problem->timeLimit . 's, <b>Memory Limit:</b> ' . $problem->memoryLimit . 'MiB</div>
                <div class="separator"></div>
                <div class="problem-statement">' . $statement . '</div>
                ' . $submitButtons . '
                <div class="box-footer">
                    ' . $statsButton . '
                    &nbsp;
                    ' . $usersButton . '
                    &nbsp;
                    ' . $tagsButton . '
                    &nbsp;
                    ' . $pdfButton . '
                </div>
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

    private function getStatusTable($submit) {
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
                    <td>' . sprintf("%.2fs", max($submit->execTime)) . '</td>
                    <td>' . sprintf("%.2f MiB", max($submit->execMemory)) . '</td>
                    <td>' . $submit->calcScore() . '</td>
                </tr>
            </table>
        ';
    }

    private function getDetailsTable($submit) {
        // If compilation error, pretty-print it and return instead of the per-test circles
        if ($submit->status == $GLOBALS['STATUS_COMPILATION_ERROR']) {
            return prettyPrintCompilationErrors($submit);
        }

        // Otherwise get information for each test and print it as a colored circle with
        // additional roll-over information
        $points = $submit->calcScores();
        $detailsTable = '<div class="centered">';
        for ($i = 1; $i < count($points); $i = $i + 1) {
            if ($i > 1 && $i % 10 == 1) {
                $detailsTable .= '<br>';
            }
            $result = $submit->results[$i];
            $tooltip =
                'Тест ' . $i . PHP_EOL .
                'Статус: ' . (is_numeric($result) ? 'OK' : $result) . PHP_EOL .
                'Точки: ' . sprintf('%.1f', $points[$i]) . PHP_EOL .
                'Време: ' . sprintf('%.2fs', $submit->execTime[$i]) . PHP_EOL .
                'Памет: ' . sprintf('%.2f MiB', $submit->execMemory[$i])
            ;
            $icon = 'WTF?';
            $background = '';
            if (is_numeric($result)) {
                $background = ($result == 1.0 ? 'dull-green' : 'dull-teal');
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

    private function getSource($problem, $submitId) {
        $redirectUrl = getProblemUrl($problem->id) . '/submits';
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        echo '<plaintext>' . $submit->getSource();
        exit(0);
    }

    function getSubmitInfoBoxContent($problem, $submitId, $redirectUrl) {
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        $statusTable = $this->getStatusTable($submit);
        $detailsTable = $this->getDetailsTable($submit);

        $author = '';
        if ($this->user->id != $submit->userId) {
            $author = '(' . $submit->userName . ')';
        }

        $source = getSourceSection($problem, $submit);

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
        $UPDATE_DELAY = 200000; // 0.2s (in microseconds)
        $MAX_UPDATES = 180 * 1000000 / $UPDATE_DELAY; // 180 seconds

        $lastContent = '';
        for ($updateId = 0; $updateId < $MAX_UPDATES; $updateId++) {
            $content = $this->getSubmitInfoBoxContent($problem, $submitId, '');
            if (strcmp($content, $lastContent) != 0) {
                sendServerEventData('content', $content);
                $lastContent = $content;
            }
            // If nothing to wait for, stop the updates
            if (strpos($content, 'fa-hourglass-start') === false && strpos($content, 'fa-spinner') === false) {
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

    private function getSubmitInfoBox($problem, $submitId) {
        $redirectUrl = '/problems/' . $problem->id . '/submits';
        if (isset($_SESSION['statusShortcut']))
            $redirectUrl = '/status';
        $updatesUrl = '/problems/' . $problem->id . '/submits/' . $submitId . '/updates';

        $content = $this->getSubmitInfoBoxContent($problem, $submitId, $redirectUrl);

        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $redirectUrl . '\');
                subscribeForUpdates(\'' . $updatesUrl . '\', \'action-form-content\');
            </script>
        ';
    }

    private function getAllSubmitsBox($problem) {
        $submits = Submit::getUserSubmits($this->user->id, $problem->id);

        $submitList = '';
        for ($i = 0; $i < count($submits); $i = $i + 1) {
            $submit = $submits[$i];
            $submitLink = '<a href="/problems/' . $problem->id . '/submits/' . $submit->id . '">' . $submit->id . '</a>';
            $submitList .= '
                <tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . $submitLink . '</td>
                    <td>' . explode(' ', $submit->submitted)[0] . '</td>
                    <td>' . explode(' ', $submit->submitted)[1] . '</td>
                    <td>' . $submit->language . '</td>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$submit->calcStatus()] . '</td>
                    <td>' . round($submit->calcScore(), 3) . '</td>
                </tr>
            ';
        }

        $content = '
            <h2><span class="blue">' . $problem->name . '</span> :: Ваши решения</h2>
            <table class="default">
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>Дата</th>
                    <th>Час</th>
                    <th>Език</th>
                    <th>Статус</th>
                    <th>Точки</th>
                </tr>
                ' . $submitList . '
            </table>
        ';

        $returnUrl = '/problems/' . $problem->id;

        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $returnUrl . '\');
            </script>
        ';
    }

    public function getContent() {
        $statusShortcut = false;
        if (isset($_SESSION['statusShortcut'])) {
            $statusShortcut = true;
            unset($_SESSION['statusShortcut']);
        }

        $this->problemName = null;
        if (isset($_GET['problemId'])) {
            $problem = Problem::get($_GET['problemId']);
            if ($problem == null) {
                redirect('/problems', 'ERROR', 'Няма задача с такъв идентификатор.');
            }
            $this->problemName = $problem->name;
            if (!canSeeProblem($this->user, $problem->visible)) {
                redirect('/problems', 'ERROR', 'Нямате права да видите тази задача.');
            }
            if ($problem->type == 'game' || $problem->type == 'relative') {
//                redirect('/games', 'ERROR', 'Поисканата задача всъщност е игра. Моля съобщете на администратор за проблема.');
                redirect(str_replace(getProblemUrl($problem->id), getGameUrl($problem->name), getCurrentUrl()));
            }

            $content = $this->getStatement($problem);
            if (isset($_GET['submits'])) {
                if ($this->user->id == -1) {
                    redirect('/problems/' . $problem->id, 'ERROR', 'Трябва да влезете в профила си за да видите тази страница.');
                }
                if (!isset($_GET['submitId'])) {
                    $content .= $this->getAllSubmitsBox($problem);
                } else {
                    if (isset($_GET['source'])) {
                        $this->getSource($problem, $_GET['submitId']);
                    } else if (isset($_GET['updates'])) {
                        $this->getSubmitUpdates($problem, $_GET['submitId']);
                    } else {
                        if ($statusShortcut)
                            $_SESSION['statusShortcut'] = true;
                        $content .= $this->getSubmitInfoBox($problem, $_GET['submitId']);
                    }
                }
            } else if (isset($_GET['stats'])) {
                $content .= $this->getStatsBox($problem);
            } else if (isset($_GET['users'])) {
                $content .= $this->getUsersBox($problem);
            } else if (isset($_GET['tags'])) {
                $content .= $this->getTagsBox($problem);
            } else if (isset($_GET['print'])) {
                $content = $this->getPrintStatement($problem);
            } else if (isset($_GET['pdf'])) {
                // Disallow this action for signed-out users.
                if ($this->user->access < $GLOBALS['ACCESS_DOWNLOAD_AS_PDF']) {
                    redirect('/problems/' . $problem->id, 'ERROR', 'Трябва да влезете в системата за да изтеглите условието.');
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