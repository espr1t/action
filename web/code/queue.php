<?php
require_once('common.php');
require_once('page.php');
require_once('grader/logic.php');

class QueuePage extends Page {
    public function getTitle() {
        return 'O(N)::Queue';
    }
    
    public function getContent() {
        $head = inBox('
            <h1>Опашка</h1>
            Информация за системата и опашката от решения.
        ');

        $time = '<div class="right smaller italic" style="padding-right: 4px;">Текущо време на системата: ' . date('H:i') . '</div>';

        $doneList = '';
        $doneList .= '<tr><td>1</td><td>ThinkCreative</td><td>Input/Output</td><td>20:38</td><td>100%</td><td>Wrong Answer</td></tr>';
        $doneList .= '<tr><td>2</td><td>espr1t</td><td>A * B</td><td>23:08</td><td>100%</td><td>Compilation Error</td></tr>';
        $doneList .= '<tr><td>3</td><td>espr1t</td><td>A * B</td><td>01:49</td><td>100%</td><td>Runtime Error</td></tr>';
        $doneList .= '<tr><td>4</td><td>ThinkCreative</td><td>Input/Output</td><td>01:52</td><td>100%</td><td>Accepted</td></tr>';
        $doneList .= '<tr><td>5</td><td>espr1t</td><td>Input/Output</td><td>01:53</td><td>100%</td><td>Wrong Answer</td></tr>';
        $doneList .= '<tr><td>6</td><td>ThinkCreative</td><td>A * B</td><td>01:54</td><td>100%</td><td>Accepted</td></tr>';

        $doneTable = '
            <table class="default">
                <tr>
                    <th style="width: 20px;">#</th>
                    <th style="width: 190px;">Потребител</th>
                    <th style="width: 190px;">Задача</th>
                    <th style="width: 70px;">Час</th>
                    <th style="width: 70px;">Прогрес</th>
                    <th>Статус</th>
                </tr>
                ' . $doneList . '
            </table>
        ';

        $done = inBox('
            <h2>Последно тествани</h2>
            ' . $doneTable . '
        ');

        $queueFile = sprintf('%s/%s', $GLOBALS['PATH_GRADER'], $GLOBALS['SUBMIT_QUEUE_FILENAME']);
        $submissions = preg_split('/\s+/', file_get_contents($queueFile));

        $queueList = '';
        for ($i = 0; $i < count($submissions); $i = $i + 1) {
            $id = intval($submissions[$i]);
            if (!$id) {
                continue;
            }
            $info = Logic::getSubmissionInfo($id);
            $progress = 0;
            foreach ($info['results'] as $result) {
                if ($result != $GLOBALS['STATUS_WAITING'] && $result != $GLOBALS['STATUS_RUNNING']) {
                    $progress = $progress + 1;
                }
            }
            $progress = sprintf('%d%%', $progress * 100 / count($info['results']));

            $queueList .= '
                <tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . getUserLink($info['userName']) . '</td>
                    <td>' . getProblemLink($info['problemId'], $info['problemName']) . '</td>
                    <td>' . $info['submissionTime'] . '</td>
                    <td>' . $progress . '</td>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$info['status']] . '</td>
                </tr>
            ';
        }

        $queueTable = '
            <table class="default">
                <tr>
                    <th style="width: 20px;">#</th>
                    <th style="width: 190px;">Потребител</th>
                    <th style="width: 190px;">Задача</th>
                    <th style="width: 70px;">Час</th>
                    <th style="width: 70px;">Прогрес</th>
                    <th>Статус</th>
                </tr>
                ' . $queueList . '
            </table>
        ';

        $queue = inBox('
            <h2>Изчакващи тестване</h2>
            ' . $queueTable . '
        ');

        $compilers = '<div class="center" style="margin-top: -6px; margin-bottom: 6px;">Информация за ползваните <a href="help#compilation">компилатори</a> и конфигурацията на <a href="help#grader">тестващата машина</a>.</div>';

        return $head . $time . $done . $queue . $compilers;
    }
    
}

?>