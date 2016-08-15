<?php

class Logic {
    public static function getProblemInfo($id) {
        $dirs = scandir($GLOBALS['PATH_PROBLEMS']);
        foreach ($dirs as $dir) {
            if ($dir == '.' || $dir == '..') {
                continue;
            }
            $fileName = sprintf("%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $dir, $GLOBALS['PROBLEM_INFO_FILENAME']);
            $info = json_decode(file_get_contents($fileName), true);
            if ($info['id'] == $id) {
                return $info;
            }
        }
        return null;
    }


    public static function getSubmissionInfo($id) {
        $infoFile = sprintf('%s/%02d/%09d.json', $GLOBALS['PATH_SUBMISSIONS'], $id / 10000000, $id);
        if (!file_exists($infoFile)) {
            return null;
        }
        $info = json_decode(file_get_contents($infoFile), true);

        $problem = Logic::getProblemInfo($info['problemId']);
        if ($problem == null) {
            return null;
        }

        $score = 0;
        $status = $GLOBALS['STATUS_ACCEPTED'];

        for ($i = 0; $i < count($info['results']); $i = $i + 1) {
            $result = $info['results'][$i];

            // The grader assigns 0/1 value for each test of IOI- and ACM-style problems and [0, 1] real fraction of the score
            // for games and relative problems. In both cases, multiplying the score of the test by this value is correct.
            if ($result >= 0) {
                $score += $result * $problem['tests'][$i]['score'];
                $info['results'][$i] = $result * $problem['tests'][$i]['score'];
            }
            // Negative scores indicate exceptional cases and are considered a zero for the test
            else {
                // The possible statuses are ordered by priority - assign the status of the problem to be the highest one
                $status = $result > $status ? $result : $status;
            }
        }

        $info['submissionDate'] = date('d. F, Y', $info['timestamp']);
        $info['submissionTime'] = date('H:i:s', $info['timestamp']);
        $info['score'] = $score;
        $info['status'] = $status;

        return $info;
    }
}

?>