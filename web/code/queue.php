<?php
require_once('db/brain.php');
require_once('entities/grader.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');

class QueuePage extends Page {
    public function getTitle() {
        return 'O(N)::Queue';
    }

    private function getQueueTable($data) {
        // Return links for submit shortcuts
        $_SESSION['queueShortcut'] = true;

        $brain = new Brain();
        $allGames = $brain->getAllGames();
        $allProblems = $brain->getAllProblems();

        $games = array();
        foreach ($allGames as $game) {
            array_push($games, intval($game['id']));
        }

        $hidden = array();
        foreach ($allGames as $game)
            if ($game['visible'] == '0') array_push($hidden, $game['id']);
        foreach ($allProblems as $problem)
            if ($problem['visible'] == '0') array_push($hidden, $problem['id']);

        $list = '';
        for ($i = 0; $i < count($data); $i = $i + 1) {
            $entry = $data[$i];

            // Hidden submits should not be shown to standard users
            if (in_array($entry['problemId'], $hidden) && !canSeeProblem($this->user, false, $entry['problemId']))
                continue;

            $regradeSubmission = '';
            if ($this->user->access >= $GLOBALS['ACCESS_REGRADE_SUBMITS']) {
                $regradeSubmission = '
                    <td style="width: 16px;">
                        <a onclick="regradeSubmission(' . $entry['submitId'] . ');" title="Regrade submission ' . $entry['submitId'] . '">
                           <i class="fa fa-refresh"></i>
                        </a>
                    </td>
                ';
            }

            $submitLink = '/problems/' . $entry['problemId'] . '/submits/' . $entry['submitId'];
            $problemLink = getProblemLink($entry['problemId'], $entry['problemName']);
            if (in_array(intval($entry['problemId']), $games)) {
                $submitLink = getGameLink($entry['problemName']) . '/submits/' . $entry['submitId'];
                $problemLink = '<a href="' . getGameLink($entry['problemName']) . '"><div class="problem">' . $entry['problemName'] . '</div></a>';
            }
            $listEntry = '
                <tr' . (in_array($entry['problemId'], $hidden) ? ' style="opacity: 0.33"' : '') . '>
                    <td><a href="' . $submitLink . '">' . $entry['submitId'] . '</a></td>
                    <td>' . getUserLink($entry['userName']) . '</td>
                    <td>' . $problemLink . '</td>
                    <td title="' . $entry['time'] . '">' . explode(' ', $entry['time'])[1] . '</td>
                    <td>' . intval($entry['progress'] * 100) . '%</td>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$entry['status']] . '</td>
                    ' . $regradeSubmission . '
                </tr>
            ';
            $list .= $listEntry;
        }

        $adminExtras = '';
        if ($this->user->access >= $GLOBALS['ACCESS_REGRADE_SUBMITS']) {
            $adminExtras = '<th style="width: 16px;"></th>';
        }

        $table = '
            <table class="default">
                <tr>
                    <th style="width: 20px;">#</th>
                    <th style="width: 170px;">Потребител</th>
                    <th style="width: 170px;">Задача</th>
                    <th style="width: 70px;">Час</th>
                    <th style="width: 70px;">Прогрес</th>
                    <th>Статус</th>
                    ' . $adminExtras . '
                </tr>
                ' . $list . '
            </table>
        ';

        return $table;
    }

    public function getContent() {
        $head = inBox('
            <h1>Опашка</h1>
            Информация за системата и опашката от решения.
        ');

        if ($GLOBALS['user']->id != -1) {
            $graderStatus = ' | <i id="graderStatus" class="fa fa-question-circle yellow" title="Проверка на грейдъра..."></i>';
            $invokeGraderCheck = '<script>updateGraderStatus();</script>';
        } else {
            $graderStatus = '';
            $invokeGraderCheck = '';
        }

        $time = '
            <div class="help-version"">
                Текущо време на системата: ' . date('H:i') . $graderStatus .'
            </div>
        ';

        $brain = new Brain();

        $latest = inBox('
            <h2>Последно тествани</h2>
            ' . $this->getQueueTable($brain->getLatest()) . '
        ');

        $pending = inBox('
            <h2>Изчакващи тестване</h2>
            ' . $this->getQueueTable($brain->getPending()) . '
        ');

        $compilers = '<div class="center" style="font-size: smaller; margin-top: -0.25rem; margin-bottom: 0.375rem;">Информация за ползваните
                <a href="help#compilation">компилатори</a> и конфигурацията на <a href="help#grader">тестващата машина</a>.</div>';


        return $head . $time . $pending . $latest . $compilers . $invokeGraderCheck;
    }

}

?>
