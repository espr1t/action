<?php
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/grader.php');
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../page.php');
require_once(__DIR__ . '/../events.php');
require_once(__DIR__ . '/../entities/submit.php');

class AdminRegradePage extends Page {
    public function getTitle() {
        return 'O(N)::Admin - Regrade';
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

    private function getRegradeView($isUpdateRequest=false) {
        $regradeList = $this->brain->getRegradeList($this->regradeId);

        $content = inBox('
            <h1>Ретестване</h1>
        ');

        // Header
        $content .= '
            <div class="box narrower">
                <table class="regradeList">
                    <tr>
                        <th style="width: 2.25rem; max-width: 2.25rem;"><i class="far fa-hashtag"></th>
                        <th style="width: 5rem; max-width: 5rem;"><i class="far fa-user-circle"></i></th>
                        <th style="width: 5rem; max-width: 5rem;"><i class="far fa-lightbulb-on"></th>
                        <th style="width: 6rem; max-width: 6rem;"><i class="far fa-calendar-alt"></th>
                        <th style="width: 6rem; max-width: 2rem;"><i class="far fa-tasks"></th>
                        <th style="width: 2rem; max-width: 2rem;"><i class="far fa-clock"></th>
                        <th style="width: 3rem; max-width: 3rem;"><i class="far fa-database"></th>
                        <th style="width: 1.75rem; max-width: 1.75rem;"><i class="far fa-check"></th>
                    </tr>
                </table>
            </div>
        ';

        $regradeCount = count($regradeList);
        if ($regradeCount == 0) {
            return $content . showMessage('ERROR', 'Няма regrade с този идентификатор!');
        }

        for ($i = 0; $i < $regradeCount; $i++) {
            $el = $regradeList[$i];
            $submit = Submit::get($el['submitId']);
            if ($submit->status != $el['newStatus']) {
                $this->brain->updateRegradeSubmit($this->regradeId, $submit);
                $el = $this->brain->getRegradeSubmit($this->regradeId, $submit);
            }

            $submitId = '<a href="/problems/' . $submit->problemId . '/submits/' . $submit->id . '">' . $submit->id . '</a>';
            $userName = getUserLink($submit->userName);
            $problemName = getProblemLink($submit->problemId, $submit->problemName);
            $oldTime = sprintf("%.2fs", floatval($el['oldTime']));
            $newTime = sprintf("%.2fs", floatval($el['newTime']));
            $oldMemory = sprintf("%.2fMB", floatval($el['oldMemory']));
            $newMemory = sprintf("%.2fMB", floatval($el['newMemory']));
            $progress = sprintf("%.0f%%", 100.0 * $submit->calcProgress());

            /*
                #DD4337 (red, if the solution was AC but now it is not)
                #129D5A (green, if the solution was not AC but now it is)
                #FCCC44 (yellow, if status was not AC but changed)
                #333333 (black, if there was an internal error)
                semi-transparent if not tested yet
            */

            $color = '';
            $opacity = 1.0;
            if (strlen($el['newStatus']) == 1) {
                $opacity = 0.5;
            } else if ($el['newStatus'] == $GLOBALS['STATUS_INTERNAL_ERROR']) {
                $color = '#333333;';
            } else if ($el['oldStatus'] != $el['newStatus']) {
                if ($el['oldStatus'] == $GLOBALS['STATUS_ACCEPTED']) {
                    $color = '#DD4337;';
                } else if ($el['newStatus'] == $GLOBALS['STATUS_ACCEPTED']) {
                    $color = '#129D5A;';
                } else {
                    $color = '#FCCC44;';
                }
            }

            $content .= '
                <div class="box narrower" style="opacity: ' . $opacity . '; background-color: ' . $color . ';">
                    <table class="regradeList">
                        <tr>
                            <td style="width: 2.25rem; max-width: 2.25rem;">' . $submitId . '</td>
                            <td style="width: 5rem; max-width: 5rem;">' . $userName . '</td>
                            <td style="width: 5rem; max-width: 5rem;">' . $problemName . '</td>
                            <td style="width: 6rem; max-width: 6rem;">' . $el['submitted'] . '<br>' . $el['regraded'] . '</td>
                            <td style="width: 6rem; max-width: 2rem;">' . $progress . '</td>
                            <td style="width: 2rem; max-width: 2rem;">' . $oldTime . '<br>' . $newTime . '</td>
                            <td style="width: 3rem; max-width: 3rem;">' . $oldMemory . '<br>' . $newMemory . '</td>
                            <td style="width: 1.75rem; max-width: 1.75rem;">' . $el['oldStatus'] . '<br>' . $el['newStatus'] . '</td>
                        </tr>
                    </table>
                </div>
            ';
        }
        $content = '
            <div id="regradeContent">
            ' . $content . '
            </div>
        ';
        if (!$isUpdateRequest) {
            $updatesUrl = '/admin/regrade/' . $this->regradeId . '/updates';
            $content .= '
            <script>
                subscribeForUpdates(\'' . $updatesUrl . '\', \'regradeContent\');
            </script>
            ';
        }
        return $content;
    }

    private function getRegradeViewUpdates() {
        $startTime = microtime(true);
        $MAX_EXEC_TIME = 28; // seconds
        $UPDATE_DELAY = 500000; // 0.5s (in microseconds)

        $lastContent = '';
        for ($updateId = 0; ; $updateId++) {
            if (microtime(true) - $startTime > $MAX_EXEC_TIME) {
                restartEventStream();
                exit();
            }

            $content = $this->getRegradeView(true);
            if (strcmp($content, $lastContent) != 0) {
                sendServerEventData('content', $content);
                $lastContent = $content;
            }
            // If nothing to wait for, stop the updates
            $finished = true;
            if (strpos($content, '>' . $GLOBALS['STATUS_WAITING'] . '<') != false) $finished = false;
            if (strpos($content, '>' . $GLOBALS['STATUS_PREPARING'] . '<') != false) $finished = false;
            if (strpos($content, '>' . $GLOBALS['STATUS_COMPILING'] . '<') != false) $finished = false;
            if (strpos($content, '>' . $GLOBALS['STATUS_TESTING'] . '<') != false) $finished = false;

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

    private function getRegradeOptions() {
        return 'TODO: Regrade Options';
    }

    private function regradeSubmit($submitId) {
        $submit = Submit::get($submitId);
        $this->brain->addRegradeSubmit($this->regradeId, $submit);
        $submit->reset();
        $submit->send();
    }

    private function regradeProblem($problemId) {
        $submits = $this->brain->getProblemSubmits($problemId);
        foreach ($submits as $submit) {
            $this->regradeSubmit($submit['id']);
        }
    }

    private function regradeLatest($problemId) {
        $submits = $this->brain->getProblemSubmits($problemId);
        $seen = array();
        for ($i = count($submits) - 1; $i >= 0; $i--) {
            if (!in_array($submits[$i]['userId'], $seen)) {
                array_push($seen, $submits[$i]['userId']);
                $this->regradeSubmit($submits[$i]['id']);
            }
        }
    }

    private function regradePending() {
        $pendingStatus = array(
            $GLOBALS['STATUS_WAITING'],
            $GLOBALS['STATUS_PREPARING'],
            $GLOBALS['STATUS_COMPILING'],
            $GLOBALS['STATUS_TESTING']
        );

        $pendingSubmits = array();
        foreach ($pendingStatus as $status) {
            $submits = $this->brain->getAllSubmits($status);
            if ($submits != null) {
                $pendingSubmits = array_merge($pendingSubmits, $submits);
            }
        }
        foreach ($pendingSubmits as $submit) {
            $this->regradeSubmit($submit['id']);
        }
    }

    public function getContent() {
        $this->clearOldRegrades();
        $this->brain = new Brain();
        $this->regradeId = randomString(5, 'abcdefghijklmnopqrstuvwxyz');

        // If no id is given, show the main page
        if (!isset($_GET['id'])) {
            return $this->getRegradeOptions();
        } else if (isset($_GET['submit'])) {
            //admin/regrade/submit/id
            $this->regradeSubmit(intval($_GET['id']));
            redirect('/admin/regrade/' . $this->regradeId);
        } else if (isset($_GET['problem'])) {
            //admin/regrade/problem/id
            $this->regradeProblem(intval($_GET['id']));
            redirect('/admin/regrade/' . $this->regradeId);
        } else if (isset($_GET['pending'])) {
            //admin/regrade/pending/id
            $this->regradePending();
            redirect('/admin/regrade/' . $this->regradeId);
        } else if (isset($_GET['latest'])) {
            //admin/regrade/latest/id
            $this->regradeLatest(intval($_GET['id']));
            redirect('/admin/regrade/' . $this->regradeId);
        } else {
            $this->regradeId = $_GET['id'];
            if (!isset($_GET['updates'])) {
                //admin/regrade/id
                return $this->getRegradeView();
            } else {
                //admin/regrade/id/updates
                $this->getRegradeViewUpdates();
            }
        }
    }

}

?>
