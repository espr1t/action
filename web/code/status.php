<?php
require_once('db/brain.php');
require_once('entities/grader.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');

class StatusPage extends Page {
    public function getTitle() {
        return 'O(N)::Status';
    }

    private function getStatusTable($data) {
        // Return links for submit shortcuts
        $_SESSION['statusShortcut'] = true;

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

            // The ID of the submit with possibly a link if accessible by the user
            $submitEl = $entry['submitId'];
            if ($this->user->id == $entry['userId'] || $this->user->access >= $GLOBALS['ACCESS_SEE_SUBMITS']) {
                $submitLink = getProblemUrl($entry['problemId']) . '/submits/' . $entry['submitId'];
                if (in_array(intval($entry['problemId']), $games)) {
                    $submitLink = getGameUrl($entry['problemName']) . '/submits/' . $entry['submitId'];
                }
                $submitEl = '<a href="' . $submitLink . '">' . $entry['submitId'] . '</a>';
            }

            // The user's nickname with a link to his/her profile
            $userEl = getUserLink($entry['userName']);

            // The problem's name with a link to the problem
            $problemEl = getProblemLink($entry['problemId'], $entry['problemName']);
            if (in_array(intval($entry['problemId']), $games)) {
                $problemEl = getGameLink($entry['problemName']);
            }

            // Optional regrade button shown to privileged users
            $regradeSubmission = '';
            if ($this->user->access >= $GLOBALS['ACCESS_REGRADE_SUBMITS']) {
                $regradeSubmission = '
                    <td style="width: 16px;">
                        <a onclick="regradeSubmission(' . $entry['submitId'] . ');" title="Regrade submission ' . $entry['submitId'] . '">
                           <i class="fa fa-sync-alt"></i>
                        </a>
                    </td>
                ';
            }

            $listEntry = '
                <tr' . (in_array($entry['problemId'], $hidden) ? ' style="opacity: 0.33"' : '') . '>
                    <td>' . $submitEl . '</td>
                    <td>' . $userEl . '</td>
                    <td>' . $problemEl . '</td>
                    <td title="' . $entry['time'] . '">' . explode(' ', $entry['time'])[1] . '</td>
                    <td>' . $entry['language'] . '</td>
                    <td>' . intval($entry['progress'] * 100) . '%</td>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$entry['status']] . '</td>
                    ' . $regradeSubmission . '
                </tr>
            ';
            $list .= $listEntry;
        }

        $adminExtras = '';
        if ($this->user->access >= $GLOBALS['ACCESS_REGRADE_SUBMITS']) {
            $adminExtras = '<th style="width: 1rem;"></th>';
        }

        return '
            <table class="default">
                <tr>
                    <th style="width: 3.5rem;">#</th>
                    <th style="width: 10rem;">Потребител</th>
                    <th style="width: 10rem;">Задача</th>
                    <th style="width: 4.5rem;">Час</th>
                    <th style="width: 4.0rem;">Език</th>
                    <th style="width: 4.5rem;">Прогрес</th>
                    <th>Статус</th>
                    ' . $adminExtras . '
                </tr>
                ' . $list . '
            </table>
        ';
    }

    public function getContent() {
        $head = inBox('
            <h1>Статус</h1>
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
            <div class="help-version">
                Текущо време на системата: ' . date('H:i') . $graderStatus .'
            </div>
        ';

        $brain = new Brain();

        $latest = inBox('
            <h2>Последно тествани</h2>
            ' . StatusPage::getStatusTable($brain->getLatest()) . '
        ');

        $pending = inBox('
            <h2>Изчакващи тестване</h2>
            ' . StatusPage::getStatusTable($brain->getPending()) . '
        ');

        $compilers = '<div class="center" style="font-size: smaller; margin-top: -0.25rem; margin-bottom: 0.375rem;">Информация за ползваните
                <a href="help#compilation">компилатори</a> и конфигурацията на <a href="help#grader">тестващата машина</a>.</div>';


        return $head . $time . $pending . $latest . $compilers . $invokeGraderCheck;
    }

}

?>
