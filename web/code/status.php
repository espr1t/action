<?php
require_once('db/brain.php');
require_once('entities/grader.php');
require_once('entities/queue.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');

class StatusPage extends Page {
    public function getTitle() {
        return 'O(N)::Status';
    }

    private function getActiveList() {
        $userList = '';
        foreach (User::getActive() as $user)
            $userList .= getUserBox($user->username);
        return $userList;
    }

    private function getStatusTable($submits) {
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
        foreach ($submits as $submit) {
            // Hidden submits should not be shown to standard users
            if (in_array($submit->problemId, $hidden) && !canSeeProblem($this->user, false))
                continue;

            // The ID of the submit with possibly a link if accessible by the user
            $submitEl = $submit->id;
            if ($this->user->id == $submit->userId || $this->user->access >= $GLOBALS['ACCESS_SEE_SUBMITS']) {
                $submitLink = getProblemUrl($submit->problemId) . '/submits/' . $submit->id;
                if (in_array(intval($submit->problemId), $games)) {
                    $submitLink = getGameUrl($submit->problemName) . '/submits/' . $submit->id;
                }
                $submitEl = '<a href="' . $submitLink . '">' . $submit->id . '</a>';
            }

            // The user's nickname with a link to his/her profile
            $userEl = getUserLink($submit->userName);

            // The problem's name with a link to the problem
            $problemEl = getProblemLink($submit->problemId, $submit->problemName);
            if (in_array(intval($submit->problemId), $games)) {
                $problemEl = getGameLink($submit->problemName);
            }

            // Optional regrade button shown to privileged users
            $regradeSubmission = '';
            if ($this->user->access >= $GLOBALS['ACCESS_REGRADE_SUBMITS']) {
                $regradeSubmission = '
                    <td style="width: 16px;">
                        <a onclick="regradeSubmission(' . $submit->id . ');" title="Regrade submission ' . $submit->id . '">
                           <i class="fa fa-sync-alt"></i>
                        </a>
                    </td>
                ';
            }

            $listEntry = '
                <tr' . (in_array($submit->problemId, $hidden) ? ' style="opacity: 0.33"' : '') . '>
                    <td>' . $submitEl . '</td>
                    <td>' . $userEl . '</td>
                    <td>' . $problemEl . '</td>
                    <td title="' . $submit->submitted . '">' . explode(' ', $submit->submitted)[1] . '</td>
                    <td>' . $submit->language . '</td>
                    <td>' . intval($submit->calcProgress() * 100) . '%</td>
                    <td>' . $GLOBALS['STATUS_DISPLAY_NAME'][$submit->status] . '</td>
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

        $active = inBox('
            <h2>Активни потребители</h2>
            ' . StatusPage::getActiveList() . '
        ');

        $latest = inBox('
            <h2>Последно тествани</h2>
            ' . StatusPage::getStatusTable(Queue::getLatest()) . '
        ');

        $pending = inBox('
            <h2>Изчакващи тестване</h2>
            ' . StatusPage::getStatusTable(Queue::getPending()) . '
        ');

        $compilers = '<div class="center" style="font-size: smaller; margin-top: -0.25rem; margin-bottom: 0.375rem;">Информация за ползваните
                <a href="help#compilation">компилатори</a> и конфигурацията на <a href="help#grader">тестващата машина</a>.</div>';


        return $head . $time . $active . $pending . $latest . $compilers . $invokeGraderCheck;
    }

}

?>
