<?php
require_once(__DIR__ . "/../db/brain.php");
require_once(__DIR__ . "/../entities/grader.php");
require_once(__DIR__ . "/../entities/queue.php");
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../page.php");
require_once(__DIR__ . "/../events.php");
require_once(__DIR__ . "/../entities/submit.php");

class AdminRegradePage extends Page {
    private string $regradeId = "invalid_id";

    public function getTitle(): string {
        return "O(N)::Admin - Regrade";
    }

    /*
    There are possible options here:
    1. Regrade submit - a single submit of a single user
        >> This is very common
    3. Regrade problem - all submits on a problem
        >> This is useful when changing the TL/ML or grader
    2. Regrade latest - latest submit of each user on a problem
        >> This is useful for games where the latest is official
    4. Regrade stuck/pending
        >> This is useful if the grader was restarted
    */

    private function clearOldRegrades() {
        // TODO
    }

    private function getRegradeView(bool $isUpdateRequest=false): string {
        $regradeList = Brain::getRegradeList($this->regradeId);

        $content = inBox("
            <h1>Ретестване</h1>
        ");

        // Header
        $content .= "
            <div class='box narrower'>
                <table class='regradeList'>
                    <tr>
                        <th style='width: 2.25rem; max-width: 2.25rem;'><i class='far fa-hashtag'></th>
                        <th style='width: 5rem; max-width: 5rem;'><i class='far fa-user-circle'></i></th>
                        <th style='width: 5rem; max-width: 5rem;'><i class='far fa-lightbulb-on'></th>
                        <th style='width: 6rem; max-width: 6rem;'><i class='far fa-calendar-alt'></th>
                        <th style='width: 6rem; max-width: 2rem;'><i class='far fa-tasks'></th>
                        <th style='width: 2rem; max-width: 2rem;'><i class='far fa-clock'></th>
                        <th style='width: 3rem; max-width: 3rem;'><i class='far fa-database'></th>
                        <th style='width: 1.75rem; max-width: 1.75rem;'><i class='far fa-check'></th>
                    </tr>
                </table>
            </div>
        ";

        $regradeCount = count($regradeList);
        if ($regradeCount == 0) {
            return $content . showNotification("ERROR", "Няма regrade с този идентификатор!");
        }

        for ($i = 0; $i < $regradeCount; $i++) {
            $el = $regradeList[$i];
            $submit = Submit::get($el["submitId"]);
            if ($submit->getStatus() != $el["newStatus"]) {
                Brain::updateRegradeSubmit($this->regradeId, $submit);
                $el = Brain::getRegradeSubmit($this->regradeId, $submit);
            }

            $oldTime = getFloatValue($el, "oldTime");
            $newTime = getFloatValue($el, "newTime");
            $oldMemory = getFloatValue($el, "oldMemory");
            $newMemory = getFloatValue($el, "newMemory");
            $oldStatus = getStringValue($el, "oldStatus");
            $newStatus = getStringValue($el, "newStatus");
            $submitted = getStringValue($el, "submitted");
            $regraded = getStringValue($el, "regraded");

            $submitId = "<a href='/problems/{$submit->getProblemId()}/submits/{$submit->getId()}'>{$submit->getId()}</a>";
            $userName = getUserLink($submit->getUserName());
            $problemName = getTaskLink($submit->getProblemId(), $submit->getProblemName());
            $oldTime = sprintf("%.2fs", $oldTime);
            $newTime = sprintf("%.2fs", $newTime);
            $oldMemory = sprintf("%.2fMB", $oldMemory);
            $newMemory = sprintf("%.2fMB", $newMemory);
            $progress = sprintf("%.0f%%", 100.0 * $submit->calcProgress());

            /*
                #DD4337 (red, if the solution was AC, but now it is not)
                #129D5A (green, if the solution was not AC, but now it is)
                #FCCC44 (yellow, if status was not AC but changed)
                #333333 (black, if there was an internal error)
            */

            $color = "";
            $opacity = 1.0;
            // Make it semi-transparent if not yet tested
            if (strlen($newStatus) == 1) {
                $opacity = 0.5;
            } else if ($newStatus == $GLOBALS["STATUS_INTERNAL_ERROR"]) {
                $color = "#333333;";
            } else if ($oldStatus != $newStatus) {
                if ($oldStatus == $GLOBALS["STATUS_ACCEPTED"]) {
                    $color = "#DD4337;";
                } else if ($newStatus == $GLOBALS["STATUS_ACCEPTED"]) {
                    $color = "#129D5A;";
                } else {
                    $color = "#FCCC44;";
                }
            }

            $content .= "
                <div class='box narrower' style='opacity: {$opacity}; background-color: {$color};'>
                    <table class='regradeList'>
                        <tr>
                            <td style='width: 2.25rem; max-width: 2.25rem;'>{$submitId}</td>
                            <td style='width: 5rem; max-width: 5rem;'>{$userName}</td>
                            <td style='width: 5rem; max-width: 5rem;'>{$problemName}</td>
                            <td style='width: 6rem; max-width: 6rem;'>{$submitted}<br>{$regraded}</td>
                            <td style='width: 6rem; max-width: 2rem;'>{$progress}</td>
                            <td style='width: 2rem; max-width: 2rem;'>{$oldTime}<br>{$newTime}</td>
                            <td style='width: 3rem; max-width: 3rem;'>{$oldMemory}<br>{$newMemory}</td>
                            <td style='width: 1.75rem; max-width: 1.75rem;'>{$oldStatus}<br>{$newStatus}</td>
                        </tr>
                    </table>
                </div>
            ";
        }
        $content = "
            <div id='regradeContent'>
                {$content}
            </div>
        ";
        if (!$isUpdateRequest) {
            $updatesUrl = "/admin/regrade/{$this->regradeId}/updates";
            $content .= "
            <script>
                subscribeForUpdates('{$updatesUrl}', 'regradeContent');
            </script>
            ";
        }
        return $content;
    }

    private function getRegradeViewUpdates(): void {
        $startTime = microtime(true);
        $MAX_EXEC_TIME = 28; // seconds
        $UPDATE_DELAY = 500000; // 0.5s (in microseconds)

        $lastContent = "";
        for ($updateId = 0; ; $updateId++) {
            if (microtime(true) - $startTime > $MAX_EXEC_TIME) {
                restartEventStream();
                exit();
            }

            $content = $this->getRegradeView(true);
            if (strcmp($content, $lastContent) != 0) {
                sendServerEventData("content", $content);
                $lastContent = $content;
            }
            // If nothing to wait for, stop the updates
            $finished = true;
            if (strpos($content, ">{$GLOBALS['STATUS_WAITING']}<") != false) $finished = false;
            if (strpos($content, ">{$GLOBALS['STATUS_PREPARING']}<") != false) $finished = false;
            if (strpos($content, ">{$GLOBALS['STATUS_COMPILING']}<") != false) $finished = false;
            if (strpos($content, ">{$GLOBALS['STATUS_TESTING']}<") != false) $finished = false;

            if ($finished) {
                terminateServerEventStream();
                exit();
            }
            // Stop updating if connection was terminated by the client
            if ($updateId % 10 == 0 && !checkServerEventClient()) {
                exit();
            }
            // Sleep until next check for changes
            usleep($UPDATE_DELAY);
        }
    }

    private function getRegradeOptions(): string {
        return "TODO: Regrade Options";
    }

    private function regrade(Submit $submit): void {
        Brain::addRegradeSubmit($this->regradeId, $submit);
        $submit->regrade(false);
    }

    private function regradeSubmit(int $submitId): void {
        $submit = Submit::get($submitId);
        $this->regrade($submit);
        Queue::update();
    }

    private function regradeProblem(int $problemId): void {
        $submits = Submit::getProblemSubmits($problemId);
        foreach ($submits as $submit) {
            $this->regrade($submit);
        }
        Queue::update();
    }

    private function regradeLatest(int $problemId): void {
        $submits = Submit::getProblemSubmits($problemId);
        $seen = array();
        for ($i = count($submits) - 1; $i >= 0; $i--) {
            if (!array_key_exists($submits[$i]->getUserId(), $seen)) {
                $seen[$submits[$i]->getUserId()] = true;
                $this->regrade($submits[$i]);
            }
        }
        Queue::update();
    }

    private function regradePending(): void {
        $submits = Submit::getPendingSubmits();
        foreach ($submits as $submit) {
            $this->regrade($submit);
        }
        Queue::update();
    }

    public function getContent(): string {
        $this->regradeId = randomString(5, "abcdefghijklmnopqrstuvwxyz");

        // Not implemented yet
        $this->clearOldRegrades();

        // If no id is given, show the main page
        if (!isset($_GET["id"])) {
            return $this->getRegradeOptions();
        } else if (isset($_GET["submit"])) {
            //admin/regrade/submit/id
            $this->regradeSubmit(intval($_GET["id"]));
            redirect("/admin/regrade/{$this->regradeId}");
        } else if (isset($_GET["problem"])) {
            //admin/regrade/problem/id
            $this->regradeProblem(intval($_GET["id"]));
            redirect("/admin/regrade/{$this->regradeId}");
        } else if (isset($_GET["pending"])) {
            //admin/regrade/pending/id
            $this->regradePending();
            redirect("/admin/regrade/{$this->regradeId}");
        } else if (isset($_GET["latest"])) {
            //admin/regrade/latest/id
            $this->regradeLatest(intval($_GET["id"]));
            redirect("/admin/regrade/{$this->regradeId}");
        } else {
            $this->regradeId = $_GET["id"];
            if (!isset($_GET["updates"])) {
                //admin/regrade/id
                return $this->getRegradeView();
            } else {
                //admin/regrade/id/updates
                $this->getRegradeViewUpdates();
            }
        }
        return "";
    }

}

?>
