<?php
require_once('logic/config.php');
require_once('logic/problem.php');
require_once('logic/submit.php');
require_once('common.php');
require_once('page.php');

class ProblemsPage extends Page {

    public function getTitle() {
        return 'O(N)::Problems';
    }
    
    public function getExtraScripts() {
        return array('/scripts/language_detector.js');
    }

    private function getAllProblems() {
        $problems = '';
        $dirs = scandir($GLOBALS['PATH_PROBLEMS']);
        foreach ($dirs as $dir) {
            if ($dir == '.' || $dir == '..') {
                continue;
            }
            $fileName = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $dir, $GLOBALS['PROBLEM_INFO_FILENAME']);
            $info = json_decode(file_get_contents($fileName), true);
            
            $solutions = 0;
            $authors = 'човек' . ($solutions == 1 ? '' : 'а');
            $problems .= '
                <div class="box narrow boxlink">
                    <a href="problems/' . $info['id'] . '" class="decorated">
                        <div class="problem-name">' . $info['name'] . '</div>
                        <div class="problem-info">
                            Сложност: <strong>' . $info['difficulty'] . '</strong><br>
                            Решена от: <strong>' . $solutions . ' ' . $authors . '</strong><br>
                            Източник: <strong>' . $info['origin'] . '</strong>
                        </div>
                    </a>
                </div>
            ';
        }
        return $problems;
    }

    private function getOrderings() {
        $order_by_training = '<a href="?order=training">тренировка</a>';
        $order_by_difficulty = '<a href="?order=difficulty">сложност</a>';
        $order_by_solutions = '<a href="?order=solutions">брой решения</a>';
        return '<div class="smaller right">Подредба по: ' . $order_by_training . ' | ' . $order_by_difficulty . ' | ' . $order_by_solutions . '</div>';
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

    private function getStatement($problem) {
        $statementFile = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
        $statement = file_get_contents($statementFile);

        $submit = $this->user->access < $GLOBALS['ACCESS_SUBMIT_SOLUTION'] ? '' : '
                <div class="problem-submit">
                    <input type="submit" value="Предай решение" onclick="showSubmitForm();" class="button button-color-blue button-large">
                    <br>
                    <a href="/problems/' . $problem->id . '/submits" style="font-size: 0.8em;">Предадени решения</a>
                </div>
        ';

        return '
            <div class="box">
                <div class="problem-title" id="problem-title">' . $problem->name . '</div>
                <div class="problem-resources">Time Limit: ' . $problem->time_limit . 's, Memory Limit: ' . $problem->memory_limit . 'MB</div>
                <div class="problem-origin">' . $problem->origin . '</div>
                <div class="separator"></div>
                <div class="problem-statement">' . $statement . '</div>
                ' . $submit . '
            </div>
        ';
    }

    private function getSubmitInfoBox($problem, $submitId) {
        if (!is_numeric($submitId)) {
            return showMessage('ERROR', 'Не съществува решение с този идентификатор!');
        }

        $submit = Submit::getSubmit($submitId);
        if ($submit == null) {
            return showMessage('ERROR', 'Не съществува решение с този идентификатор!');
        }

        if ($submit->userId != $this->user->id) {
            return showMessage('ERROR', 'Нямате достъп до това решение!');
        }

        if ($submit->problemId != $problem->id) {
            return showMessage('ERROR', 'Решението не е по поисканата задача!');
        }

        $scoredSubmit = $problem->scoreSubmit($submit);

        $color = 'gray';
        if ($scoredSubmit['status'] <= $GLOBALS['STATUS_INTERNAL_ERROR']) {
            $color = 'red';
        }
        if ($scoredSubmit['status'] <= $GLOBALS['STATUS_ACCEPTED']) {
            $color = 'green';
        }

        $summaryTable = '
            <table class="default ' . $color . '">
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Статус на задачата</th>
                    <th style="width: 100px;">Точки</th>
                </tr>
                <tr>
                    <td>-</td>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$scoredSubmit['status']] . '</td>
                    <td>' . $scoredSubmit['score'] . '</td>
                </tr>
            </table>
        ';

        $testResults = '';
        for ($i = 0; $i < count($scoredSubmit['results']); $i = $i + 1) {
            $result = $scoredSubmit['results'][$i];
            $testResults .= '
                <tr>
                    <td>' . $i . '</td>
                    <td>' . ($result < 0 ? $GLOBALS['STATUS_DISPLAY_NAME'][$result] : 'OK') . '</td>
                    <td>' . ($result < 0 ? 0 : $result) . '</td>
                </tr>
            ';
        }

        $detailedTable = '
            <table class="default">
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Статус по тестове</th>
                    <th style="width: 100px;">Точки</th>
                </tr>
                ' . $testResults . '
            </table>
        ';

        return '
            <div id="submissionStatus" class="submission-status fade-in">
                <div class="submission-close" onclick="hideSubmitStatus(' . $problem->id . ');"><i class="fa fa-close fa-fw"></i></div>
                <h2><span class="blue">' . $problem->name . '</span> :: Статус на решение</h2>
                <div class="right smaller">' . explode(' ', $submit->time)[0] . ' | ' . explode(' ', $submit->time)[1] . '</div>
                <br>
                ' . $summaryTable . '
                <br>
                ' . $detailedTable . '
            </div>
            <script>
                showSubmitStatus(' . $problem->id . ');
            </script>
        ';
    }

    private function getAllSubmitsBox($problem) {
        $submits = Submit::getUserSubmits($this->user->id, $problem->id);

        $submitList = '';
        for ($i = 0; $i < count($submits); $i = $i + 1) {
            $submit = $submits[$i];
            $scoredSubmit = $problem->scoreSubmit($submit);
            $submitLink = '<a href="/problems/' . $problem->id . '/submits/' . $submit->id . '">' . $submit->id . '</a>';
            $submitList .= '
                <tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . explode(' ', $submit->time)[0] . '</td>
                    <td>' . explode(' ', $submit->time)[1] . '</td>
                    <td>' . $submitLink . '</td>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$scoredSubmit['status']] . '</td>
                    <td>' . $scoredSubmit['score'] . '</td>
                </tr>
            ';
        }
        $submitTable = '
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

        return '
            <div id="submissionStatus" class="submission-status fade-in">
                <div class="submission-close" onclick="hideSubmitStatus(' . $problem->id . ');"><i class="fa fa-close fa-fw"></i></div>
                <h2><span class="blue">' . $problem->name . '</span> :: Ваши решения</h2>
                ' . $submitTable . '
            </div>
            <script>
                showSubmitStatus(' . $problem->id . ');
            </script>
        ';
    }

    public function getContent() {
        if (isset($_GET['problem'])) {
            $problem = Problem::get($_GET['problem']);
            if ($problem == null) {
                return $this->getMainPage();
            }

            $content = $this->getStatement($problem);
            if (isset($_GET['submits'])) {
                if (!isset($_GET['id'])) {
                    $content .= $this->getAllSubmitsBox($problem);
                } else {
                    $content .= $this->getSubmitInfoBox($problem, $_GET['id']);
                }
            }
            return $content;
        }
        return $this->getMainPage();
    }
}

?>