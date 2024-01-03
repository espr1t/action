<?php
require_once("db/brain.php");
require_once("entities/grader.php");
require_once("entities/queue.php");
require_once("config.php");
require_once("common.php");
require_once("page.php");

class StatusPage extends Page {
    public function getTitle(): string {
        return "O(N)::Status";
    }

    private function getActiveList(): string {
        $userList = "";
        foreach (User::getActive() as $user)
            $userList .= getUserBox($user->getUsername());
        return $userList;
    }

    /** @param Submit[] $submits */
    private function getStatusTable(array $submits): string {
        // Return links for submit shortcuts
        $_SESSION["statusShortcut"] = true;

        $allGames = Problem::getAllGames();
        $allTasks = Problem::getAllTasks();

        $games = array();
        foreach ($allGames as $game) {
            array_push($games, $game->getId());
        }

        $hidden = array();
        foreach ($allGames as $game)
            if (!$game->getVisible()) array_push($hidden, $game->getId());
        foreach ($allTasks as $task)
            if (!$task->getVisible()) array_push($hidden, $task->getId());

        $adminExtras = $this->user->getAccess() >= $GLOBALS["ACCESS_REGRADE_SUBMITS"] ? "<th style='width: 1rem;'></th>" : "";

        $tableRows = "
                <tr>
                    <th style='width: 3.5rem;'>#</th>
                    <th style='width: 10rem;'>Потребител</th>
                    <th style='width: 10rem;'>Задача</th>
                    <th style='width: 4.5rem;'>Час</th>
                    <th style='width: 4.0rem;'>Език</th>
                    <th style='width: 4.5rem;'>Прогрес</th>
                    <th>Статус</th>
                    {$adminExtras}
                </tr>
        ";

        foreach ($submits as $submit) {
            // System submits are hidden by default
            // Also all submits on hidden problems should be hidden
            $shouldHide = $submit->getUserId() == 0 || in_array($submit->getProblemId(), $hidden);
            if ($shouldHide && $this->user->getAccess() < $GLOBALS["ACCESS_HIDDEN_PROBLEMS"])
                continue;

            // The ID of the submit with possibly a link if accessible by the user
            $submitEl = $submit->getId();
            if ($this->user->getId() == $submit->getUserId() || $this->user->getAccess() >= $GLOBALS["ACCESS_SEE_SUBMITS"]) {
                $submitLink = in_array($submit->getProblemId(), $games) ?
                    getGameUrl($submit->getProblemName()) . "/submits/{$submit->getId()}" :
                    getTaskUrl($submit->getProblemId()) . "/submits/{$submit->getId()}";
                $submitEl = "<a href='{$submitLink}'>{$submit->getId()}</a>";
            }

            // The user's nickname with a link to his/her profile
            $userEl = getUserLink($submit->getUserName());

            // The problem's name with a link to the problem
            $problemEl = in_array($submit->getProblemId(), $games) ?
                getGameLink($submit->getProblemName()) :
                getTaskLink($submit->getProblemId(), $submit->getProblemName());

            // Optional regrade button shown to privileged users
            $regradeSubmission = "";
            if ($this->user->getAccess() >= $GLOBALS["ACCESS_REGRADE_SUBMITS"]) {
                $regradeSubmission = "
                    <td style='width: 16px;'>
                        <span class='tooltip--left' data-tooltip='Regrade submission {$submit->getId()}'>
                            <a onclick='regradeSubmission({$submit->getId()});'>
                               <i class='fa fa-sync-alt'></i>
                            </a>
                        </span>
                    </td>
                ";
            }

            $rowOptions = $shouldHide ? "style='opacity: 0.33'" : "";
            $tableRows .= "
                <tr {$rowOptions}>
                    <td>{$submitEl}</td>
                    <td>{$userEl}</td>
                    <td>{$problemEl}</td>
                    <td><span class='tooltip--top' data-tooltip='{$submit->getSubmitted()}'>" . explode(" ", $submit->getSubmitted())[1] . "</span></td>
                    <td>{$submit->getLanguage()}</td>
                    <td>" . intval($submit->calcProgress() * 100) . "%</td>
                    <td>{$GLOBALS['STATUS_DISPLAY_NAME'][$submit->getStatus()]}</td>
                    {$regradeSubmission}
                </tr>
            ";
        }

        return "
            <table class='default'>
                {$tableRows}
            </table>
        ";
    }

    public function getContent(): string {
        $head = inBox("
            <h1>Статус</h1>
            Информация за системата и опашката от решения.
        ");

        if ($this->user->getId() != -1) {
            $graderStatus = " | <span id='graderStatusTooltip' class='tooltip--left' data-tooltip='Проверка на грейдъра...' style='font-style: normal'><i id='graderStatusIcon' class='fa fa-question-circle yellow'></i></span>";
            $invokeGraderCheck = "<script>updateGraderStatus();</script>";
        } else {
            $graderStatus = "";
            $invokeGraderCheck = "";
        }

        $time = "
            <div class='help-version'>
                Текущо време на системата: " . date("H:i") . " {$graderStatus}
            </div>
        ";

        $active = inBox("
            <h2>Активни потребители</h2>
            " . StatusPage::getActiveList() . "
        ");

        $latest = inBox("
            <h2>Последно тествани</h2>
            " . StatusPage::getStatusTable(Queue::getLatest()) . "
        ");

        $pending = inBox("
            <h2>Изчакващи тестване</h2>
            " . StatusPage::getStatusTable(Queue::getPending()) . "
        ");

        $compilers = "
            <div class='center' style='font-size: smaller; margin-top: -0.25rem; margin-bottom: 0.375rem;'>
                Информация за ползваните <a href='help#compilation'>компилатори</a> и конфигурацията на <a href='help#grader'>тестващата машина</a>.
            </div>
        ";

        return $head . $time . $active . $pending . $latest . $compilers . $invokeGraderCheck;
    }

}

?>
