<?php
require_once('db/brain.php');
require_once('entities/problem.php');
require_once('entities/submit.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');
require_once('events.php');

class ProblemsPage extends Page {
    private $problem;

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

    public function getProblemBox(&$problemInfo, $problemSolutions) {
        $statusIcon = '<i class="fa fa-circle-thin gray" title="Още не сте пробвали да решите тази задача."></i>';
        $serviceUserSolutions = 0;
        foreach ($problemSolutions as $problemSolution) {
            if ($problemSolution['userId'] == $this->user->id) {
                $statusIcon = '<i class="fa fa-check green" title="Вече сте решили успешно тази задача!."></i>';
            }
            if ($problemSolution['userId'] <= 1) {
                $serviceUserSolutions |= (1 << $problemSolution['userId']);
            }
        }

        $difficulty = '';
        switch ($problemInfo['difficulty']) {
            case 'trivial':
                $difficulty = '<i class="fa fa-leaf" title="Trivial"></i>';
                break;
            case 'easy':
                $difficulty = '<i class="fa fa-paper-plane" title="Easy"></i>';
                break;
            case 'medium':
                $difficulty = '<i class="fa fa-balance-scale" title="Medium"></i>';
                break;
            case 'hard':
                $difficulty = '<i class="fa fa-tint" title="Hard"></i>';
                break;
            case 'brutal':
                $difficulty = '<i class="fa fa-paw" title="Brutal"></i>';
                break;
            default:
                $difficulty = '<i class="fa fa-question" title="Unknown"></i>';
        }

        $problemInfo['solutions'] = (count($problemSolutions) - popcount($serviceUserSolutions));
        $solutions = '<span style="font-weight: bold;" title="Решена от ' . $problemInfo['solutions'] .
                        ' човек' . ($problemInfo['solutions'] != 1 ? 'a' : '') . '">' . $problemInfo['solutions'] . '</span>';

        $box = '
            <a href="/problems/' . $problemInfo['id'] . '" class="decorated">
                <div class="box narrow boxlink">
                        <div class="problem-status">' . $statusIcon . '</div>
                        <div class="problem-name">' . $problemInfo['name'] . '</div>
                        <div class="problem-stats">' . $difficulty . ' | ' . $solutions . '</div>
                </div>
            </a>
        ';
        // Make hidden problems grayed out (actually visible only to admins).
        if ($problemInfo['visible'] == '0') {
            $box = '<div style="opacity: 0.5;">' . $box . '</div>';
        }
        return $box;
    }

    private function getAllProblems() {
        $brain = new Brain();
        $problemsInfo = $brain->getAllProblems();
        $allProblemsSubmits = $brain->getAllSubmits($GLOBALS['STATUS_ACCEPTED']);
        $problemSolvedBy = array();
        foreach ($problemsInfo as $problem)
            $problemSolvedBy[$problem['id']] = array();
        foreach ($allProblemsSubmits as $submit) {
            // Apparently a submit on a game
            if (!array_key_exists($submit['problemId'], $problemSolvedBy)) {
                continue;
            }
            // Check if already counting the solution by this user
            $alreadyIn = false;
            foreach ($problemSolvedBy[$submit['problemId']] as $author)
                $alreadyIn = $alreadyIn || $author['userId'] == $submit['userId'];
            if (!$alreadyIn)
                array_push($problemSolvedBy[$submit['problemId']], $submit);
        }

        for ($i = 0; $i < count($problemsInfo); $i += 1) {
            $problemSolutions = $problemSolvedBy[$problemsInfo[$i]['id']];
            // The number of user solutions is calculated in getProblemBox()
            $problemsInfo[$i]['box'] = $this->getProblemBox($problemsInfo[$i], $problemSolutions);
        }

        // Order by solutions or difficulty, if requested
        if (isset($_GET['order'])) {
            $numericDifficulty = array('trivial' => 0, 'easy' => 1, 'medium' => 2, 'hard' => 3, 'brutal' => 4);
            if ($_GET['order'] == 'solutions') {
                usort($problemsInfo, function($left, $right) use($numericDifficulty) {
                    if ($left['solutions'] != $right['solutions'])
                        return $right['solutions'] - $left['solutions'];
                    if ($left['difficulty'] != $right['difficulty'])
                        return $numericDifficulty[$left['difficulty']] - $numericDifficulty[$right['difficulty']];
                    return $left['id'] - $right['id'];
                });
            }
            if ($_GET['order'] == 'difficulty') {
                usort($problemsInfo, function($left, $right) use($numericDifficulty) {
                    if ($left['difficulty'] != $right['difficulty'])
                        return $numericDifficulty[$left['difficulty']] - $numericDifficulty[$right['difficulty']];
                    if ($left['solutions'] != $right['solutions'])
                        return $right['solutions'] - $left['solutions'];
                    return $left['id'] - $right['id'];
                });
            }
        }

        $problems = '';
        foreach ($problemsInfo as $problemInfo) {
            // Don't show hidden problems
            if (!canSeeProblem($this->user, $problemInfo['visible'] == '1', $problemInfo['id']))
                continue;
            $problems .= $problemInfo['box'];
        }
        return $problems;
    }

    private function getOrderings() {
        $order_by_difficulty = '<a href="?order=difficulty">сложност</a>';
        $order_by_solutions = '<a href="?order=solutions">брой решения</a>';
        return '<div class="right" style="font-size: smaller;">Подреди по: ' . $order_by_difficulty . ' | ' . $order_by_solutions . '</div>';
    }

    private function getMainPage() {
        $text = '<h1>Задачи</h1>
                 Тук можете да намерите списък с всички задачи от тренировката.
        ';
        $header = inBox($text);
        $orderings = $this->getOrderings();
        $problems = $this->getAllProblems();
        return $header . $orderings . $problems;
    }

    private function getStatsBox($problem) {
        $brain = new Brain();
        $submits = $brain->getProblemSubmits($problem->id, 'all');

        $bestTime = 1e10;
        $bestMemory = 1e10;
        $acceptedSubmits = 0;
        $allSubmits = 0;
        foreach ($submits as $submit) {
            $allSubmits += 1;
            if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                $acceptedSubmits += 1;
                $time = array_map(function ($el) {return floatval($el);}, explode(',', $submit['exec_time']));
                $memory = array_map(function ($el) {return floatval($el);}, explode(',', $submit['exec_memory']));
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
        $brain = new Brain();
        $submits = $brain->getProblemSubmits($problem->id, 'AC');

        $tableContent = '';
        $users = array();
        foreach ($submits as $submit) {
            // Skip system user
            if ($submit['userId'] < 1)
                continue;

            if (array_key_exists($submit['userId'], $users))
                continue;
            $users[$submit['userId']] = true;

            $tableContent .= '
                <tr>
                    <td>' . getUserLink($submit['userName']) . '</td>
                    <td>' . $submit['language'] . '</td>
                    <td>' . max(array_map(function ($el) {return floatval($el);}, explode(',', $submit['exec_time']))) . '</td>
                    <td>' . max(array_map(function ($el) {return floatval($el);}, explode(',', $submit['exec_memory']))) . '</td>
                    <td>' . $submit['submitted'] . '</td>
                </tr>
            ';
        }

        $content = '
            <h2><span class="blue">' . $problem->name . '</span> :: Потребители</h2>
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

    private function getStatement($problem) {
        $statementFile = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
        $statement = file_get_contents($statementFile);

        $submitFormContent = '
            <h2><span class="blue">' . $problem->name . '</span> :: Предаване на Решение</h2>
            <div class="center">
                <textarea name="source" class="submit-source" cols=80 rows=24 id="source"></textarea>
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

        $visibilityRestriction = $problem->visible ? '' : '<i class="fa fa-eye-slash" title="This problem is hidden."></i>';
        return '
            <div class="box' . ($GLOBALS['user']->id == -1 ? '' : ' box-problem') . '">
                <div class="problem-visibility">' . $visibilityRestriction . '</div>
                <div class="problem-title" id="problem-title">' . $problem->name . '</div>
                <div class="problem-origin">' . $problem->origin . '</div>
                <div class="problem-resources"><b>Time Limit:</b> ' . $problem->timeLimit . 's, <b>Memory Limit:</b> ' . $problem->memoryLimit . 'MiB</div>
                <div class="separator"></div>
                <div class="problem-statement">' . $statement . '</div>
                ' . $submitButtons . '
            </div>
            <div class="problem-stats-link"">
                <a class="decorated" href="/problems/' . $problem->id . '/stats"><i class="fa fa-info-circle"></i></a>
                <a class="decorated" href="/problems/' . $problem->id . '/users"><i class="fa fa-users"></i></a>
            </div>
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
                    <td>' . sprintf("%.2fs", max($submit->exec_time)) . '</td>
                    <td>' . sprintf("%.2f MiB", max($submit->exec_memory)) . '</td>
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
                'Време: ' . sprintf('%.2fs', $submit->exec_time[$i]) . PHP_EOL .
                'Памет: ' . sprintf('%.2f MiB', $submit->exec_memory[$i])
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
                $icon = '<i class="fa fa-warning"></i>';
            }
            $detailsTable .= '<div class="test-result tooltip--top background-' . $background . '" data-tooltip="' . $tooltip . '">' . $icon . '</div>';
        }
        $detailsTable .= '</div>';
        $detailsTable = '<div class="test-result-wrapper">' . $detailsTable . '</div>';

        return $detailsTable;
    }

    function getSubmitInfoBoxContent($problem, $submitId, $redirectUrl) {
        $submit = getSubmitWithChecks($this->user, $submitId, $problem, $redirectUrl);
        $statusTable = $this->getStatusTable($submit);
        $detailsTable = $this->getDetailsTable($submit);

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
        $lastContent = '';
        while (true) {
            $content = $this->getSubmitInfoBoxContent($problem, $submitId, '');
            if ($content != $lastContent) {
                sendServerEventData('content', $content);
                $lastContent = $content;
            }
            // If nothing to wait for, stop the updates
            if (strpos($content, 'fa-hourglass-start') === false && strpos($content, 'fa-spinner') === false) {
                terminateServerEventStream();
                return;
            }
            // Sleep for 0.5 seconds until next check for changes
            sleep(0.5);
        }
    }

    private function getSubmitInfoBox($problem, $submitId) {
        $redirectUrl = '/problems/' . $problem->id . '/submits';
        if (isset($_SESSION['queueShortcut']))
            $redirectUrl = '/queue';
        $updatesUrl = '/problems/' . $problem->id . '/submits/' . $submitId . '/updates';

        $content = $this->getSubmitInfoBoxContent($problem, $submitId, $redirectUrl);

        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $redirectUrl . '\');
                subscribeForUpdates(\'' . $updatesUrl . '\');
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
                    <td>' . explode(' ', $submit->submitted)[0] . '</td>
                    <td>' . explode(' ', $submit->submitted)[1] . '</td>
                    <td>' . $submitLink . '</td>
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
                    <th>Дата</th>
                    <th>Час</th>
                    <th>Идентификатор</th>
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
        $queueShortcut = false;
        if (isset($_SESSION['queueShortcut'])) {
            $queueShortcut = true;
            unset($_SESSION['queueShortcut']);
        }

        $this->problemName = null;
        if (isset($_GET['problemId'])) {
            $problem = Problem::get($_GET['problemId']);
            $this->problemName = $problem->name;
            if ($problem == null) {
                redirect('/problems', 'ERROR', 'Няма задача с такъв идентификатор.');
            }
            if (!canSeeProblem($this->user, $problem->visible, $problem->id)) {
                redirect('/problems', 'ERROR', 'Нямате права да видите тази задача.');
            }
            if ($problem->type == 'game' || $problem->type == 'relative') {
                redirect('/games', 'ERROR', 'Поисканата задача всъщност е игра. Моля съобщете на администратор за проблема.');
            }

            $content = $this->getStatement($problem);
            if (isset($_GET['submits'])) {
                if ($this->user->id == -1) {
                    redirect('/problems/' . $problem->id, 'ERROR', 'Трябва да влезете в профила си за да видите тази страница.');
                }
                if (!isset($_GET['submitId'])) {
                    $content .= $this->getAllSubmitsBox($problem);
                } else {
                    if (isset($_GET['updates'])) {
                        $this->getSubmitUpdates($problem, $_GET['submitId']);
                    } else {
                        if ($queueShortcut)
                            $_SESSION['queueShortcut'] = true;
                        $content .= $this->getSubmitInfoBox($problem, $_GET['submitId']);
                    }
                }
            } else if (isset($_GET['stats'])) {
                $content .= $this->getStatsBox($problem);
            } else if (isset($_GET['users'])) {
                $content .= $this->getUsersBox($problem);
            }
            return $content;
        }
        return $this->getMainPage();
    }
}

?>