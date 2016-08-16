<?php
require_once('common.php');
require_once('page.php');
require_once('grader/logic.php');

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
            $fileName = sprintf("%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $dir, $GLOBALS['PROBLEM_INFO_FILENAME']);
            $info = json_decode(file_get_contents($fileName), true);
            
            $solutions = count($info['accepted']);
            $authors = 'човек' . ($solutions == 1 ? '' : 'а');
            $problems .= '
                <div class="box narrow boxlink">
                    <a href="problems/' . $info['id'] . '" class="decorated">
                        <div class="problem-name">' . $info['name'] . '</div>
                        <div class="problem-info">
                            Сложност: <strong>' . $info['difficulty'] . '</strong><br>
                            Решена от: <strong>' . $solutions . ' ' . $authors . '</strong><br>
                            Източник: <strong>' . $info['source'] . '</strong>
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

    private function getStatement($problemInfo) {
        $statementFile = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problemInfo['folder'], $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
        $statement = file_get_contents($statementFile);

        $submit = $this->user->getAccess() < $GLOBALS['ACCESS_SUBMIT_SOLUTION'] ? '' : '
                <div class="problem-submit">
                    <input type="submit" value="Предай решение" onclick="showSubmitForm();" class="button button-color-blue button-large">
                    <br>
                    <a href="/problems/' . $problemInfo['id'] . '/submissions" style="font-size: 0.8em;">Предадени решения</a>
                </div>
        ';

        return '
            <div class="box">
                <div class="problem-title" id="problem-title">' . $problemInfo['name'] . '</div>
                <div class="problem-resources">Time Limit: ' . $problemInfo['time_limit'] . 's, Memory Limit: ' . $problemInfo['memory_limit'] . 'MB</div>
                <div class="problem-source">' . $problemInfo['source'] . '</div>
                <div class="separator"></div>
                <div class="problem-statement">' . $statement . '</div>
                ' . $submit . '
            </div>
        ';
    }

    private function getSubmission($problemInfo, $id) {
        if (!is_numeric($id)) {
            return showMessage('ERROR', 'Не съществува решение с този идентификатор!');
        }

        $submissionInfo = Logic::getSubmissionInfo($id);
        if ($submissionInfo == null) {
            return showMessage('ERROR', 'Не съществува решение с този идентификатор!');
        }

        if ($submissionInfo['userId'] != $this->user->getId()) {
            return showMessage('ERROR', 'Нямате достъп до това решение!');
        }

        $color = 'green';
        if ($submissionInfo['status'] >= $GLOBALS['STATUS_RUNNING']) {
            $color = 'gray';
        } else if ($submissionInfo['status'] >= $GLOBALS['STATUS_WRONG_ANSWER']) {
            $color = 'red';
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
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$submissionInfo['status']] . '</td>
                    <td>' . $submissionInfo['score'] . '</td>
                </tr>
            </table>
        ';

        $testResults = '';
        for ($i = 0; $i < count($submissionInfo['results']); $i = $i + 1) {
            $result = $submissionInfo['results'][$i];
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
                <div class="submission-close" onclick="hideSubmissionStatus(' . $problemInfo['id'] . ');"><i class="fa fa-close fa-fw"></i></div>
                <h2><span class="blue">' . $problemInfo['name'] . '</span> :: Статус на решение</h2>
                <div class="right smaller">' . $submissionInfo['date'] . ' | ' . $submissionInfo['time'] . '</div>
                <br>
                ' . $summaryTable . '
                <br>
                ' . $detailedTable . '
            </div>
            <script>
                showSubmissionStatus(' . $problemInfo['id'] . ');
            </script>
        ';
    }

    private function getAllSubmissions($problemInfo) {
        $submissionList = '';
        $counter = 0;
        foreach ($this->user->getSubmissions() as $submissionId) {
            $submission = Logic::getSubmissionInfo($submissionId);
            if ($submission['problemId'] == $problemInfo['id']) {
                $counter = $counter + 1;
                $submissionLink = '<a href="/problems/' . $problemInfo['id'] . '/submissions/' . $submissionId . '">' . $submissionId . '</a>';
                $submissionList .= '
                    <tr>
                        <td>' . $counter . '</td>
                        <td>' . $submission['date'] . '</td>
                        <td>' . $submission['time'] . '</td>
                        <td>' . $submissionLink . '</td>
                        <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$submission['status']] . '</td>
                        <td>' . $submission['score'] . '</td>
                    </tr>
                ';
            }
        }
        $submissionTable = '
            <table class="default">
                <tr>
                    <th>#</th>
                    <th>Дата</th>
                    <th>Час</th>
                    <th>Идентификатор</th>
                    <th>Статус</th>
                    <th>Точки</th>
                </tr>
                ' . $submissionList . '
            </table>
        ';

        return '
            <div id="submissionStatus" class="submission-status fade-in">
                <div class="submission-close" onclick="hideSubmissionStatus(' . $problemInfo['id'] . ');"><i class="fa fa-close fa-fw"></i></div>
                <h2><span class="blue">' . $problemInfo['name'] . '</span> :: Ваши решения</h2>
                ' . $submissionTable . '
            </div>
            <script>
                showSubmissionStatus(' . $problemInfo['id'] . ');
            </script>
        ';
    }

    public function getContent() {
        if (isset($_GET['problem'])) {
            $problemInfo = Logic::getProblemInfo($_GET['problem']);
            if ($problemInfo == null) {
                return $this->getMainPage();
            }

            $content = $this->getStatement($problemInfo);
            if (isset($_GET['submission'])) {
                if ($_GET['submission'] == 'all') {
                    $content .= $this->getAllSubmissions($problemInfo);
                } else {
                    $content .= $this->getSubmission($problemInfo, $_GET['submission']);
                }
            }
            return $content;
        }
        return $this->getMainPage();
    }
}

?>