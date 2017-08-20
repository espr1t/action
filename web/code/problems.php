<?php
require_once('db/brain.php');
require_once('entities/problem.php');
require_once('entities/submit.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');

class ProblemsPage extends Page {
    private $problem;

    public function getTitle() {
        if ($this->problem == null) {
            return 'O(N)::Problems';
        }
        return 'O(N)::' . $this->problem->name;
    }
    
    public function getExtraScripts() {
        return array('/scripts/language_detector.js');
    }

    private function canSeeProblem($user, $problemVisible, $problemId) {
        if ($problemVisible)
            return true;
        return $user->access >= $GLOBALS['ACCESS_HIDDEN_PROBLEMS'];
    }

    private function getAllProblems() {
        $brain = new Brain();
        $problemsInfo = $brain->getAllProblems();
        $allProblemsSubmits = $brain->getAllSubmits('AC');
        $problemSubmits = array();
        foreach ($allProblemsSubmits as $submit) {
            if (!array_key_exists($submit['problemId'], $problemSubmits))
                $problemSubmits[$submit['problemId']] = array();
            $alreadyIn = false;
            foreach ($problemSubmits[$submit['problemId']] as $prevSubmit)
                $alreadyIn = $alreadyIn || $prevSubmit['userId'] == $submit['userId'];
            if (!$alreadyIn)
                array_push($problemSubmits[$submit['problemId']], $submit);
        }

        for ($i = 0; $i < count($problemsInfo); $i += 1) {
            $problemSolutions = $problemSubmits[$problemsInfo[$i]['id']];
            $statusIcon = '<i class="fa fa-circle-thin gray" title="Още не сте пробвали да решите тази задача."></i>';
            $serviceUserSolutions = 0;
            foreach ($problemSolutions as $problemSolution) {
                if ($problemSolution['userId'] == $GLOBALS['user']->id) {
                    $statusIcon = '<i class="fa fa-check green" title="Вече сте решили успешно тази задача!."></i>';
                }
                if ($problemSolution['userId'] <= 1) {
                    $serviceUserSolutions |= (1 << $problemSolution['userId']);
                }
            }
            $difficulty = '';
            switch ($problemsInfo[$i]['difficulty']) {
                case 'trivial':
                    $difficulty = '<i class="fa fa-child" title="Trivial"></i>';
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
            $numSolutions = (count($problemSolutions) - popcount($serviceUserSolutions));
            $solutions = '<i class="fa fa-users" title="Решена от ' . $numSolutions . ' човек' . ($numSolutions != 1 ? 'a' : '') . '"></i> ' . $numSolutions;

            $problemsInfo[$i]['box'] = '
                <a href="problems/' . $problemsInfo[$i]['id'] . '" class="decorated">
                    <div class="box narrow boxlink">
                            <div class="problem-status">' . $statusIcon . '</div>
                            <div class="problem-name">' . $problemsInfo[$i]['name'] . '</div>
                            <div class="problem-stats">' . $difficulty . ' &nbsp; '  . $solutions . '</div>
                    </div>
                </a>
            ';
            // Make hidden problems grayed out (actually visible only to admins).
            if ($problemsInfo[$i]['visible'] == '0') {
                $problemsInfo[$i]['box'] = '
                    <div style="opacity: 0.5;">
                    ' . $problemsInfo[$i]['box'] . '
                    </div>
                ';
            }
            $problemsInfo[$i]['solutions'] = count($problemSolutions);
        }

        // Order by solutions or difficulty, if requested
        if (isset($_GET['order'])) {
            $numericDifficulty = array('trivial' => 0, 'easy' => 1, 'medium' => 2, 'hard' => 3, 'brutal' => 4);
            if ($_GET['order'] == 'solutions') {
                usort($problemsInfo, function($left, $right) use($numericDifficulty) {
                    if ($left['solutions'] != $right['solutions'])
                        return $left['solutions'] < $right['solutions'];
                    if ($left['difficulty'] != $right['difficulty'])
                        return $numericDifficulty[$left['difficulty']] > $numericDifficulty[$right['difficulty']];
                    return $left['id'] > $right['id'];
                });
            }
            if ($_GET['order'] == 'difficulty') {
                usort($problemsInfo, function($left, $right) use($numericDifficulty) {
                    if ($left['difficulty'] != $right['difficulty'])
                        return $numericDifficulty[$left['difficulty']] > $numericDifficulty[$right['difficulty']];
                    if ($left['solutions'] != $right['solutions'])
                        return $left['solutions'] < $right['solutions'];
                    return $left['id'] > $right['id'];
                });
            }
        }

        $problems = '';
        foreach ($problemsInfo as $problemInfo) {
            // Don't show hidden problems
            if (!$this->canSeeProblem($this->user, $problemInfo['visible'] == '1', $problemInfo['id']))
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

    private function getSubmitInfoBox($problem, $submitId) {
        $returnUrl = '/problems/' . $problem->id . '/submits';
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
        $problemStatus = $GLOBALS['STATUS_DISPLAY_NAME'][$status];

        $summaryTable = '
            <table class="default ' . $color . '">
                <tr>
                    <th>Статус на задачата</th>
                    <th style="width: 100px;">Време</th>
                    <th style="width: 100px;">Памет</th>
                    <th style="width: 100px;">Точки</th>
                </tr>
                <tr>
                    <td>' . $problemStatus . '</td>
                    <td>' . sprintf("%.2fs", max($submit->exec_time)) . '</td>
                    <td>' . sprintf("%.2f MiB", max($submit->exec_memory)) . '</td>
                    <td>' . $submit->calcScore() . '</td>
                </tr>
            </table>
        ';

        $scores = $submit->calcScores();

        $detailedTable = '<div class="centered">';
        for ($i = 1; $i < count($scores); $i = $i + 1) {
            if ($i > 1 && $i % 10 == 1) {
                $detailedTable .= '<br>';
            }
            $result = $submit->results[$i];
            $title = 'Тест ' . $i . '\n' .
                     'Статус: ' . (is_numeric($result) ? 'OK' : $result) . '\n' .
                     'Точки: ' . $scores[$i] . '\n' .
                     'Време: ' . sprintf("%.2fs", $submit->exec_time[$i]) . '\n' .
                     'Памет: ' . sprintf("%.2f MiB", $submit->exec_memory[$i]) . '\n' .
            '';
            $icon = 'WTF?';
            $class = 'test-result background-';
            if (is_numeric($result)) {
                $class .= ($result == 1.0 ? 'dull-green' : 'dull-teal');
                $icon = '<i class="fa fa-check"></i>';
            } else if ($result == $GLOBALS['STATUS_WAITING'] || $result == $GLOBALS['STATUS_PREPARING'] || $result == $GLOBALS['STATUS_COMPILING']) {
                $class .= 'dull-gray';
                $icon = '<i class="fa fa-circle-o"></i>';
            } else if ($result == $GLOBALS['STATUS_TESTING']) {
                $class .= 'dull-gray';
                $icon = '<i class="fa fa-spinner fa-pulse"></i>';
            } else if ($result == $GLOBALS['STATUS_WRONG_ANSWER']) {
                $class .= 'dull-red';
                $icon = '<i class="fa fa-times"></i>';
            } else if ($result == $GLOBALS['STATUS_TIME_LIMIT']) {
                $class .= 'dull-red';
                $icon = '<i class="fa fa-clock-o"></i>';
            } else if ($result == $GLOBALS['STATUS_MEMORY_LIMIT']) {
                $class .= 'dull-red';
                $icon = '<i class="fa fa-database"></i>';
            } else if ($result == $GLOBALS['STATUS_RUNTIME_ERROR']) {
                $class .= 'dull-red';
                $icon = '<i class="fa fa-bug"></i>';
            } else if ($result == $GLOBALS['STATUS_COMPILATION_ERROR']) {
                $class .= 'dull-red';
                $icon = '<i class="fa fa-code"></i>';
            } else if ($result == $GLOBALS['STATUS_INTERNAL_ERROR']) {
                $class .= 'dull-black';
                $icon = '<i class="fa fa-warning"></i>';
            }
            $detailedTable .= '<div class="' . $class . ' test-status-tooltip" data-title="' . $title . '">' . $icon . '</div>';
        }
        $detailedTable .= '</div>';

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

        $this->problem = null;
        if (isset($_GET['problemId'])) {
            $this->problem = Problem::get($_GET['problemId']);
            if ($this->problem == null) {
                redirect('/problems', 'ERROR', 'Няма задача с такъв идентификатор.');
            }
            if (!$this->canSeeProblem($this->user, $this->problem->visible, $this->problem->id)) {
                redirect('/problems', 'ERROR', 'Нямате права да видите тази задача.');
            }
            if ($this->problem->type == 'game') {
                redirect('/games', 'ERROR', 'Поисканата задача всъщност е игра. Моля съобщете на администратор за проблема.');
            }

            $content = $this->getStatement($this->problem);
            if (isset($_GET['submits'])) {
                if ($this->user->id == -1) {
                    redirect('/problems/' . $this->problem->id, 'ERROR', 'Трябва да влезете в профила си за да видите тази страница.');
                }
                if (!isset($_GET['submitId'])) {
                    $content .= $this->getAllSubmitsBox($this->problem);
                } else {
                    if ($queueShortcut)
                        $_SESSION['queueShortcut'] = true;
                    $content .= $this->getSubmitInfoBox($this->problem, $_GET['submitId']);
                }
            } else if (isset($_GET['stats'])) {
                $content .= $this->getStatsBox($this->problem);
            } else if (isset($_GET['users'])) {
                $content .= $this->getUsersBox($this->problem);
            }
            return $content;
        }
        return $this->getMainPage();
    }
}

?>