<?php
require_once('logic/brain.php');
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
        $brain = new Brain();
        $problemsInfo = $brain->getAllProblems();

        $problemsInfo[0]['id'] = 0;
        $problemsInfo[0]['name'] = 'A * B Problem';
        $problemsInfo[0]['difficulty'] = 'trivial';
        $problemsInfo[0]['origin'] = 'popular';
        $problemsInfo[1]['id'] = 1;
        $problemsInfo[1]['name'] = 'Input/Output';
        $problemsInfo[1]['difficulty'] = 'easy';
        $problemsInfo[1]['origin'] = 'informatika.bg training';

        $problems = '';
        foreach ($problemsInfo as $problemInfo) {
            $problemSolutions = $brain->getProblemSubmits($problemInfo['id'], $GLOBALS['STATUS_ACCEPTED']);
            $solutions = count($problemSolutions);
            $problems .= '
                <div class="box narrow boxlink">
                    <a href="problems/' . $problemInfo['id'] . '" class="decorated">
                        <div class="problem-name">' . $problemInfo['name'] . '</div>
                        <div class="problem-info">
                            Сложност: <strong>' . $problemInfo['difficulty'] . '</strong><br>
                            Решена от: <strong>' . $solutions . ' човек' . ($solutions == 1 ? '' : 'а') . '</strong><br>
                            Източник: <strong>' . $problemInfo['origin'] . '</strong>
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

        $submitFormContent = '
            <h2><span class="blue">' . $problem->name . '</span> :: Предаване на Решение</h2>
            <div class="center">
                <textarea name="source" class="submit-source" cols=80 rows=24 id="source"></textarea>
            </div>
            <div class="italic right" style="font-size: 0.8em;">Detected language: <span id="language">?</span></div>
            <div class="center"><input type="submit" value="Изпрати" onclick="submitSubmitForm();" class="button button-color-red"></div>
        ';

        $submitButtons = $this->user->access < $GLOBALS['ACCESS_SUBMIT_SOLUTION'] ? '' : '
            <script>
                function showForm() {
                    showSubmitForm(`' . $submitFormContent . '`);
                }
            </script>
            <div class="problem-submit">
                <input type="submit" value="Предай решение" onclick="showForm();" class="button button-color-blue button-large">
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
                ' . $submitButtons . '
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

        $content = '
            <h2><span class="blue">' . $problem->name . '</span> :: Статус на решение</h2>
            <div class="right smaller">' . explode(' ', $submit->time)[0] . ' | ' . explode(' ', $submit->time)[1] . '</div>
            <br>
            ' . $summaryTable . '
            <br>
            ' . $detailedTable . '
        ';

        $redirect = '/problems/' . $problem->id . '/submits';

        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $redirect . '\');
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

        $redirect = '/problems/' . $problem->id;

        return '
            <script>
                showActionForm(`' . $content . '`, \'' . $redirect . '\');
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