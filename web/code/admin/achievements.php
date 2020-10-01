<?php
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../page.php');
require_once(__DIR__ . '/../games.php');
require_once(__DIR__ . '/../ranking.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/user.php');
require_once(__DIR__ . '/../entities/problem.php');

class AdminAchievementsPage extends Page {
    private Brain $brain;
    private $achievementTitle = array();
    private $PRIME_NUMBERS = array();
    private $FIBONACCI_NUMBERS = array();
    private $PERFECT_NUMBERS = array();
    private $POWER_OF_TWO_NUMBERS = array();
    private $POWER_OF_TEN_NUMBERS = array();
    private $PI_PREFIX_NUMBERS = array();
    private $E_PREFIX_NUMBERS = array();
    private $TRICKY_PROBLEMS = array();

    public function getTitle() {
        return 'O(N)::Admin';
    }

    public function getExtraScripts() {
        return array('/scripts/admin.js');
    }

    public function addAchievement($user, $achived, $key, $time) {
        // Add the achievement to the DB
        $this->brain->addAchievement($user->id, $key, $time);
        // Mark the achievement as achieved for the user
        $achieved[$key] = true;
        // Record the achievement in the logs
        $logMessage = sprintf('User %s unlocked achievement "%s".', $user->username, $this->achievementTitle[$key]);
        write_log($GLOBALS['LOG_ACHIEVEMENTS'], $logMessage);
    }


    public function initSpecialNumbers() {
        $limit = 100000;
        $isPrime = array_fill(0, $limit, true);
        $isPrime[0] = $isPrime[1] = false;
        for ($num = 2; $num * $num < $limit; $num++) {
            if ($isPrime[$num]) {
                for ($comp = $num * $num; $comp < $limit; $comp += $num)
                    $isPrime[$comp] = false;
            }
        }

        for ($num = 0; $num < $limit; $num++)
            if ($isPrime[$num]) array_push($this->PRIME_NUMBERS, $num);

        $this->FIBONACCI_NUMBERS = array(0, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144, 233, 377,
            610, 987, 1597, 2584, 4181, 6765, 10946, 17711, 28657, 46368, 75025, 121393,
            196418, 317811, 514229, 832040, 1346269, 2178309, 3524578, 5702887, 9227465,
            14930352, 24157817, 39088169, 63245986, 102334155);

        $this->PERFECT_NUMBERS = array(6, 28, 496, 8128, 33550336);
        $this->POWER_OF_TWO_NUMBERS = array(1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048,
            4096, 8192, 16384, 32768, 65536, 131072, 262144, 524288, 1048576, 2097152, 4194304,
            8388608, 16777216, 33554432, 67108864, 134217728, 268435456, 536870912);
        $this->POWER_OF_TEN_NUMBERS = array(1, 10, 100, 1000, 10000, 100000, 1000000, 10000000, 100000000);
        $this->PI_PREFIX_NUMBERS = array(3, 31, 314, 3141, 31415, 314159, 3141592, 31415926, 314159265);
        $this->E_PREFIX_NUMBERS = array(2, 27, 271, 2718, 27182, 271828, 2718281, 27182818, 271828182);
    }

    public function initTrickyProblems() {
        $trickyNames = array(
            'Sheep', 'Ssssss', 'Bribes', 'Sequence Members', 'Wordrow', 'Next', 'Shades', 'Seats',
            'Bazinga!', 'Crosses', 'Collatz', 'Passwords', 'Digit Holes', 'Directory Listing'
        );
        $problems = $this->brain->getAllProblems();
        foreach ($problems as $problem) {
            if (in_array($problem['name'], $trickyNames))
                $this->TRICKY_PROBLEMS[$problem['id']] = true;
        }
    }

    // Has submitted or solved X times/probems from a given type (tags, difficulty, or any)
    public function achievementSubmits($user, $achieved, $key, $submitIds, $limit) {
        if (!array_key_exists($key, $achieved)) {
            if (count($submitIds) >= $limit) {
                $date = $this->submits[$submitIds[$limit - 1]]['submitted'];
                $this->addAchievement($user, $achieved, $key, $date);
            }
        }
    }

    // Has solved problems of all tags or difficulties
    public function achievementAllTagsDifficulties($user, $achieved, $key, $solvedPerType) {
        if (!array_key_exists($key, $achieved)) {
            $date = '';
            foreach ($solvedPerType as $type => $solvedIds) {
                if (count($solvedIds) == 0)
                    return;
                $date = max(array($date, $this->submits[$solvedIds[0]]['submitted']));
            }
            $this->addAchievement($user, $achieved, $key, $date);
        }
    }

    // Solved X problems in Y minutes
    public function achievementSpeed($user, $achieved, $key, $acSubmitIds, $count, $limit) {
        if (!array_key_exists($key, $achieved)) {
            $solvedCount = count($acSubmitIds);
            for ($i = 0; $i + $count <= $solvedCount; $i++) {
                $ts1 = strtotime($this->submits[$acSubmitIds[$i]]['submitted']);
                $ts2 = strtotime($this->submits[$acSubmitIds[$i + $count - 1]]['submitted']);
                if ($ts2 - $ts1 <= $limit) {
                    $date = $this->submits[$acSubmitIds[$i + $count - 1]]['submitted'];
                    $this->addAchievement($user, $achieved, $key, $date);
                    return;
                }
            }
        }
    }

    // Solved various training sections
    public function achievementsTraining($user, $achieved, $firstACSubmitPerProblem) {
        $completed = 0;
        $latestDate = '';
        foreach ($this->training as $section) {
            $key = 'T_' . $section['key'];
            if (!array_key_exists($key, $achieved)) {
                $date = '';
                $sectionProblems = explode(',', $section['problems']);
                foreach ($sectionProblems as $problemId) {
                    if (!array_key_exists($problemId, $firstACSubmitPerProblem)) {
                        $date = '';
                        break;
                    }
                    $submit = $this->submits[$firstACSubmitPerProblem[$problemId]];
                    if ($date == '' || $date < $submit['submitted'])
                        $date = $submit['submitted'];
                }
                if ($date != '') {
                    $this->addAchievement($user, $achieved, $key, $date);
                    $completed++;
                    if ($latestDate == '' || $latestDate < $date)
                        $latestDate = $date;
                }
            } else {
                $completed++;
            }
        }
        if (!array_key_exists('GRADU8', $achieved)) {
            if ($completed == count($this->training)) {
                $this->addAchievement($user, $achieved, 'GRADU8', $latestDate);
            }
        }
    }

    // Has played X games
    public function achievementPlayedGame($user, $achieved, $key, $submitIds, $limit) {
        if (!array_key_exists($key, $achieved)) {
            $played = array();
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                if ($this->isGame[$submit['problemId']]) {
                    if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                        if (!array_key_exists($submit['problemId'], $played)) {
                            $played[$submit['problemId']] = true;
                            if (count($played) >= $limit) {
                                $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                                return;
                            }
                        }
                    }
                }
            }
        }
    }

    // Has won a game
    public function achievementWonGame($user, $achieved, $key, $submitIds) {
        if (!array_key_exists($key, $achieved)) {
            $date = '';
            foreach ($this->standings as $game => $ranking) {
                // Games that haven't yet been played
                if (count($ranking) == 0) {
                    continue;
                }
                // Anything other
                if ($ranking[0]['user'] == $user->id) {
                    foreach ($submitIds as $submitId) {
                        $submit = $this->submits[$submitId];
                        if ($submit['id'] == $ranking[0]['submit']) {
                            if ($date == '' || $date > $submit['submitted']) {
                                $date = $submit['submitted'];
                            }
                        }
                    }
                }
            }
            // Iterate all games in order to find the earliest winning submit
            if ($date != '') {
                $this->addAchievement($user, $achieved, $key, $date);
            }
        }
    }

    // Has married Sasho
    public function achievementMarried($user, $achieved, $key) {
        if (!array_key_exists($key, $achieved)) {
            if ($user->username == 'kopche') {
                $this->addAchievement($user, $achieved, $key, '2018-09-02');
            }
        }
    }

    // Has registered
    public function achievementRegistered($user, $achieved, $key) {
        if (!array_key_exists($key, $achieved)) {
            $this->addAchievement($user, $achieved, $key, $user->registered);
        }
    }

    // Has reported a problem
    public function achievementReported($user, $achieved, $key, $reportIds) {
        if (!array_key_exists($key, $achieved)) {
            if (count($reportIds) > 0) {
                $date = $this->reports[$reportIds[0]]['date'];
                $this->addAchievement($user, $achieved, $key, $date);
            }
        }
    }

    // Has over 1000 actions on the site
    public function achievementActive($user, $achieved, $key) {
        if (!array_key_exists($key, $achieved)) {
            if ($user->actions >= 1000) {
                $this->addAchievement($user, $achieved, $key, date('Y-m-d H:i:s'));
            }
        }
    }

    // Has ran over 10000 tests
    public function achievementTested($user, $achieved, $key, $submitIds) {
        if (!array_key_exists($key, $achieved)) {
            $total = 0;
            foreach ($submitIds as $submitId) {
                $total += count(explode(',', $this->submits[$submitId]['results']));
                if ($total >= 10000) {
                    $date = $this->submits[$submitId]['submitted'];
                    $this->addAchievement($user, $achieved, $key, $date);
                    return;
                }
            }
        }
    }

    // Ranked in top X
    public function achievementRanked($user, $achieved, $key, $limit) {
        if (!array_key_exists($key, $achieved)) {
            $maxPos = min(array($limit, count($this->ranking)));
            for ($pos = 0; $pos < $maxPos; $pos++) {
                if ($this->ranking[$pos]['id'] == $user->id) {
                    $this->addAchievement($user, $achieved, $key, date('Y-m-d H:i:s'));
                    return;
                }
            }
        }
    }

    // Was the first to solve a problem
    public function achievementVirgin($user, $achieved, $key) {
        if (!array_key_exists($key, $achieved)) {
            $date = '';
            foreach ($this->firstACSubmit as $problemId => $submitId) {
                if ($this->submits[$submitId]['userId'] == $user->id) {
                    if ($date == '' || $date > $this->submits[$submitId]['submitted'])
                        $date = $this->submits[$submitId]['submitted'];
                }
            }
            // Iterate all problems so we get the earliest virgin submit
            if ($date != '') {
                $this->addAchievement($user, $achieved, $key, $date);
            }
        }
    }

    // Submitted a problem in unusual time (late in the night or early in the morning)
    public function achievementUnusualTime($user, $achieved, $key, $submitIds, $lower, $upper) {
        if (!array_key_exists($key, $achieved)) {
            foreach ($submitIds as $submitId) {
                $hour = date('H', strtotime($this->submits[$submitId]['submitted']));
                if ($hour >= $lower && $hour < $upper) {
                    $date = $this->submits[$submitId]['submitted'];
                    $this->addAchievement($user, $achieved, $key, $date);
                    return;
                }
            }
        }
    }

    // Solved problems in 3 different languages
    public function achievementRainbow($user, $achieved, $key, $acSubmitIds) {
        if (!array_key_exists($key, $achieved)) {
            $langs = array();
            foreach ($acSubmitIds as $submitId) {
                $submit = $this->submits[$submitId];
                if (!array_key_exists($submit['language'], $langs)) {
                    $langs[$submit['language']] = true;
                    if (count($langs) >= 3) {
                        $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                        return;
                    }
                }
            }
        }
    }

    // Registered more than a year ago
    public function achievementOldtimer($user, $achieved, $key) {
        if (!array_key_exists($key, $achieved)) {
            $anniversary = strtotime($user->registered) + 365 * 24 * 60 * 60;
            if (time() >= $anniversary) {
                $date = date('Y-m-d', $anniversary);
                $this->addAchievement($user, $achieved, $key, $date);
            }
        }
    }

    // Submit on Christmas, New Year, or user's birthday
    public function achievementDate($user, $achieved, $key, $submitIds, $target) {
        if (!array_key_exists($key, $achieved)) {
            foreach ($submitIds as $submitId) {
                if (date('m-d', strtotime($this->submits[$submitId]['submitted'])) == $target) {
                    $date = $this->submits[$submitId]['submitted'];
                    $this->addAchievement($user, $achieved, $key, $date);
                    return;
                }
            }
        }
    }

    // Got three different types of errors on a problem
    public function achievementSoWrong($user, $achieved, $key, $submitIds) {
        if (!array_key_exists($key, $achieved)) {
            $errors = array();
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                $errorMask = 0;
                if (array_key_exists($submit['problemId'], $errors))
                    $errorMask = $errors[$submit['problemId']];
                if ($submit['status'] == 'WA')
                     $errorMask |= (1 << 0);
                if ($submit['status'] == 'RE')
                    $errorMask |= (1 << 1);
                if ($submit['status'] == 'TL')
                    $errorMask |= (1 << 2);
                if ($submit['status'] == 'CE')
                    $errorMask |= (1 << 3);
                if ($submit['status'] == 'ML')
                    $errorMask |= (1 << 4);
                if ($submit['status'] == 'IE')
                    $errorMask |= (1 << 5);
                $errors[$submit['problemId']] = $errorMask;

                if (popcount($errorMask) >= 3) {
                    $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                    return;
                }
            }
        }
    }

    // Got several different types of errors in a single submit
    public function achievementDisaster($user, $achieved, $key, $submitIds, $limit) {
        if (!array_key_exists($key, $achieved)) {
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                $results = explode(',', $submit['results']);
                $errorMask = 0;
                foreach($results as $result) {
                    if (is_numeric($result))
                        $errorMask |= (1 << 0);
                    else if ($result == 'WA')
                        $errorMask |= (1 << 1);
                    else if ($result == 'RE')
                        $errorMask |= (1 << 2);
                    else if ($result == 'TL')
                        $errorMask |= (1 << 3);
                    else if ($result == 'CE')
                        $errorMask |= (1 << 4);
                    else if ($result == 'ML')
                        $errorMask |= (1 << 5);
                    else if ($result == 'IE')
                        $errorMask |= (1 << 6);
                }
                if (popcount($errorMask) >= $limit) {
                    $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                    return;
                }
            }
        }
    }

    // X unsuccessful submits on a problem
    public function achievementUnsuccess($user, $achieved, $key, $submitIds, $target) {
        if (!array_key_exists($key, $achieved)) {
            $unsuccessful = array();
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                if (!$this->isGame[$submit['problemId']]) {
                    if (!array_key_exists($submit['problemId'], $unsuccessful))
                        $unsuccessful[$submit['problemId']] = 0;
                    if ($submit['status'] != $GLOBALS['STATUS_ACCEPTED'])
                        $unsuccessful[$submit['problemId']]++;
                    if ($unsuccessful[$submit['problemId']] >= $target) {
                        $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                        return;
                    }
                }
            }
        }
    }

    // Solved X tasks on the first try
    public function achievementAccurate($user, $achieved, $key, $submitIds, $target) {
        if (!array_key_exists($key, $achieved)) {
            $onFirstTry = 0;
            $submitted = array();
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                if (!array_key_exists($submit['problemId'], $submitted)) {
                    $submitted[$submit['problemId']] = true;
                    if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                        $onFirstTry++;
                        if ($onFirstTry >= $target) {
                            $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                            return;
                        }
                    }
                }
            }
        }
    }

    // Solved 5 tricky problems on the first try
    public function achievementPedantic($user, $achieved, $key, $submitIds) {
        if (!array_key_exists($key, $achieved)) {
            $onFirstTry = 0;
            $submitted = array();
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                if (!array_key_exists($submit['problemId'], $submitted)) {
                    $submitted[$submit['problemId']] = true;
                    if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                        if (array_key_exists($submit['problemId'], $this->TRICKY_PROBLEMS)) {
                            $onFirstTry++;
                            if ($onFirstTry >= 5) {
                                $this->addAchievement($user, $achieved, $key, $$submit['submitted']);
                                return;
                            }
                        }
                    }
                }
            }
        }
    }

    // Filled all profile information
    public function achievementProfile($user, $achieved, $key) {
        if (!array_key_exists($key, $achieved)) {
            if ($user->email && $user->town && $user->country && $user->gender && $user->birthdate != '0000-00-00') {
                // TODO: Set the achievemnt date to the one this actually happened once info edit is available
                $this->addAchievement($user, $achieved, $key, $user->registered);
            }
        }
    }

    // Solved again an already accepted task with a new solution
    public function achievementReimplemented($user, $achieved, $key, $acSubmitIds) {
        if (!array_key_exists($key, $achieved)) {
            $prevSource = array();
            foreach ($acSubmitIds as $submitId) {
                $submit = $this->submits[$submitId];
                $source = $this->sources[$submitId];
                if (array_key_exists($submit['problemId'], $prevSource)) {
                    if ($prevSource[$submit['problemId']] != $source['source']) {
                        $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                        return;
                    }
                }
                $prevSource[$submit['problemId']] = $source['source'];
            }
        }
    }

    private function isOffByOne($str1, $str2) {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        if (abs($len1 - $len2) > 1 || $str1 == $str2)
            return false;

        $idx1 = $idx2 = 0;
        while ($idx1 < $len1 && $idx2 < $len2 && $str1[$idx1] == $str2[$idx2]) {
            $idx1++;
            $idx2++;
        }
        if ($idx1 >= $len1 || $idx2 >= $len2)
            return true;
        if ($len1 > $len2) {
            $idx2++;
        } else if ($len2 > $len1) {
            $idx1++;
        } else {
            $idx1++;
            $idx2++;
        }
        while ($idx1 < $len1 && $idx2 < $len2 && $str1[$idx1] == $str2[$idx2]) {
            $idx1++;
            $idx2++;
        }
        if ($idx1 < $len1 || $idx2 < $len2)
            return false;
        return true;
    }

    // Fixed an off-by-one error to get a problem accepted
    public function achievementOffByOne($user, $achieved, $key, $submitIds) {
        if (!array_key_exists($key, $achieved)) {
            $prevProblemSubmit = array();
            foreach ($submitIds as $currId) {
                $currSubmit = $this->submits[$currId];
                if (array_key_exists($currSubmit['problemId'], $prevProblemSubmit)) {
                    $prevId = $prevProblemSubmit[$currSubmit['problemId']];
                    $prevSubmit = $this->submits[$prevId];
                    if ($currSubmit['status'] == $GLOBALS['STATUS_ACCEPTED'] &&
                        $prevSubmit['status'] != $GLOBALS['STATUS_ACCEPTED'] &&
                        $prevSubmit['status'] != $GLOBALS['STATUS_COMPILATION_ERROR']) {
                        $currSource = $this->sources[$currId]['source'];
                        $prevSource = $this->sources[$prevId]['source'];
                        if ($this->isOffByOne($currSource, $prevSource)) {
                            $this->addAchievement($user, $achieved, $key, $currSubmit['submitted']);
                            return;
                        }
                    }
                }
                $prevProblemSubmit[$currSubmit['problemId']] = $currId;
            }
        }
    }

    // Sent solutions from 3 different IPs (hopefully locations, but not necessarily)
    public function achievementDifferentLocations($user, $achieved, $key, $submitIds) {
        if (!array_key_exists($key, $achieved)) {
            $ips = array();
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                if ($submit['ip'] && !array_key_exists($submit['ip'], $ips)) {
                    $ips[$submit['ip']] = true;
                    if (count($ips) >= 3) {
                        $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                        return;
                    }
                }
            }
        }
    }

    // Solved a specific problem
    public function achievementProblem($user, $achieved, $key, $acSubmitIds, $problemName) {
        if (!array_key_exists($key, $achieved)) {
            foreach ($this->problems as $problem) {
                if ($problem['name'] == $problemName) {
                    foreach ($acSubmitIds as $submitId) {
                        $submit = $this->submits[$submitId];
                        if ($submit['problemId'] == $problem['id']) {
                            $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                            return;
                        }
                    }
                    return;
                }
            }
        }
    }

    public function achievementSpecialIDSubmit($user, $achieved, $key, $submitIds, $special) {
        if (!array_key_exists($key, $achieved)) {
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                if (in_array($submit['id'], $special)) {
                    $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                    return;
                }
            }
        }
    }

    public function achievementCodeLength($user, $achieved, $key, $submitIds, $lenLimit) {
        if (!array_key_exists($key, $achieved)) {
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                // Don't use userACSubmits as they don't include submits on games
                if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                    $source = $this->sources[$submitId];
                    $length = substr_count($source['source'], "\n") + 1;
                    if (($lenLimit < 0 && $length <= -$lenLimit) || ($lenLimit > 0 && $length >= $lenLimit)) {
                        $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                        return;
                    }
                }
            }
        }
    }

    public function achievementPersistence($user, $achieved, $key, $submitIds, $dayLimit) {
        if (!array_key_exists($key, $achieved)) {
            $consecutive = 1;
            $previous = (int)(strtotime('1970-01-01 00:00:00') / (24 * 60 * 60));
            foreach ($submitIds as $submitId) {
                $submit = $this->submits[$submitId];
                $current = (int)(strtotime($submit['submitted']) / (24 * 60 * 60));
                if ($current - $previous == 1) {
                    $consecutive++;
                    if ($consecutive >= $dayLimit) {
                        $this->addAchievement($user, $achieved, $key, $submit['submitted']);
                        return;
                    }
                } else if ($current - $previous > 1) {
                    $consecutive = 1;
                }
                $previous = $current;
            }
        }
    }

    public function updateAll($user, $userSubmits, $userAchievements, $userReports) {
        // Already achieved
        $achieved = array();
        foreach ($userAchievements as $achievementId) {
            $achievement = $this->achievements[$achievementId];
            $achieved[$achievement['achievement']] = true;
        }

        // Solved problems
        $userACSubmits = array();
        $userFirstACSubmits = array();
        $userFirstACSubmitPerProblem = array();
        foreach ($userSubmits as $submitId) {
            $submit = $this->submits[$submitId];
            // Skip games in this list
            if ($this->isGame[$submit['problemId']])
                continue;
            if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                array_push($userACSubmits, $submitId);
                if (!array_key_exists($submit['problemId'], $userFirstACSubmitPerProblem)) {
                    $userFirstACSubmitPerProblem[$submit['problemId']] = $submitId;
                    array_push($userFirstACSubmits, $submitId);
                }
            }
        }

        // Solved per problem difficulty and tags
        $userSolvedPerTag = array();
        foreach ($this->problemTagsCnt as $tag => $cnt)
            $userSolvedPerTag[$tag] = array();

        $userSolvedPerDiff = array();
        foreach ($this->problemDifficultiesCnt as $difficulty => $cnt)
            $userSolvedPerDiff[$difficulty] = array();

        foreach ($userFirstACSubmits as $submitId) {
            $submit = $this->submits[$submitId];
            // Tags
            foreach ($this->problemTags[$submit['problemId']] as $tag)
                array_push($userSolvedPerTag[$tag], $submitId);
            // Difficulty
            $difficulty = $this->problemDifficulties[$submit['problemId']];
            array_push($userSolvedPerDiff[$difficulty], $submitId);
        }

        // Achievements that cannot be recalculated accurately (date is lost):
        // ACTIVE, RANK01, RANK10

        // Number of submits and solutions achievements
        $this->achievementSubmits($user, $achieved, 'SUB1E0', $userSubmits, 1);
        $this->achievementSubmits($user, $achieved, 'SUB1E1', $userSubmits, 10);
        $this->achievementSubmits($user, $achieved, 'SUB1E2', $userSubmits, 100);
        $this->achievementSubmits($user, $achieved, 'SUB1E3', $userSubmits, 1000);
        $this->achievementSubmits($user, $achieved, 'SOL1E0', $userFirstACSubmits, 1);
        $this->achievementSubmits($user, $achieved, 'SOL1E1', $userFirstACSubmits, 10);
        $this->achievementSubmits($user, $achieved, 'SOL1E2', $userFirstACSubmits, 100);
        $this->achievementSubmits($user, $achieved, 'ALLTSK', $userFirstACSubmits, count($this->problems));

        // Problems difficulty achievements
        $this->achievementSubmits($user, $achieved, '1TRIVL', $userSolvedPerDiff['trivial'], 1);
        $this->achievementSubmits($user, $achieved, '1EASYY', $userSolvedPerDiff['easy'], 1);
        $this->achievementSubmits($user, $achieved, '1MEDIU', $userSolvedPerDiff['medium'], 1);
        $this->achievementSubmits($user, $achieved, '1HARDD', $userSolvedPerDiff['hard'], 1);
        $this->achievementSubmits($user, $achieved, '1BRUTL', $userSolvedPerDiff['brutal'], 1);

        $this->achievementSubmits($user, $achieved, 'ALLTRV', $userSolvedPerDiff['trivial'], $this->problemDifficultiesCnt['trivial']);
        $this->achievementSubmits($user, $achieved, 'ALLESY', $userSolvedPerDiff['easy'], $this->problemDifficultiesCnt['easy']);
        $this->achievementSubmits($user, $achieved, 'ALLMED', $userSolvedPerDiff['medium'], $this->problemDifficultiesCnt['medium']);
        $this->achievementSubmits($user, $achieved, 'ALLHRD', $userSolvedPerDiff['hard'], $this->problemDifficultiesCnt['hard']);
        $this->achievementSubmits($user, $achieved, 'ALLBRL', $userSolvedPerDiff['brutal'], $this->problemDifficultiesCnt['brutal']);
        $this->achievementAllTagsDifficulties($user, $achieved, 'ALLDIF', $userSolvedPerDiff);

        // Problems tags achievements
        $this->achievementSubmits($user, $achieved, 'ALLIMP', $userSolvedPerTag['implement'], $this->problemTagsCnt['implement']);
        $this->achievementSubmits($user, $achieved, 'ALLSRC', $userSolvedPerTag['search'], $this->problemTagsCnt['search']);
        $this->achievementSubmits($user, $achieved, 'ALLDPR', $userSolvedPerTag['dp'], $this->problemTagsCnt['dp']);
        $this->achievementSubmits($user, $achieved, 'ALLGRF', $userSolvedPerTag['graph'], $this->problemTagsCnt['graph']);
        $this->achievementSubmits($user, $achieved, 'ALLMAT', $userSolvedPerTag['math'], $this->problemTagsCnt['math']);
        $this->achievementSubmits($user, $achieved, 'ALLGEO', $userSolvedPerTag['geometry'], $this->problemTagsCnt['geometry']);
        $this->achievementSubmits($user, $achieved, 'ALLADH', $userSolvedPerTag['ad-hoc'], $this->problemTagsCnt['ad-hoc']);
        $this->achievementSubmits($user, $achieved, 'ALLFLW', $userSolvedPerTag['flow'], $this->problemTagsCnt['flow']);
        $this->achievementSubmits($user, $achieved, 'ALLDAC', $userSolvedPerTag['divconq'], $this->problemTagsCnt['divconq']);
        $this->achievementSubmits($user, $achieved, 'ALLSTR', $userSolvedPerTag['strings'], $this->problemTagsCnt['strings']);
        $this->achievementSubmits($user, $achieved, 'ALLSOR', $userSolvedPerTag['sorting'], $this->problemTagsCnt['sorting']);
        $this->achievementSubmits($user, $achieved, 'ALLGRD', $userSolvedPerTag['greedy'], $this->problemTagsCnt['greedy']);
        $this->achievementSubmits($user, $achieved, 'ALLGAM', $userSolvedPerTag['game'], $this->problemTagsCnt['game']);
        $this->achievementSubmits($user, $achieved, 'ALLDST', $userSolvedPerTag['datastruct'], $this->problemTagsCnt['datastruct']);
        $this->achievementSubmits($user, $achieved, 'ALLPNP', $userSolvedPerTag['np'], $this->problemTagsCnt['np']);
        $this->achievementAllTagsDifficulties($user, $achieved, 'ALLTAG', $userSolvedPerTag);

        // Solving speed achievements
        $this->achievementSpeed($user, $achieved, 'TWOTEN', $userFirstACSubmits, 2, 10 * 60);
        $this->achievementSpeed($user, $achieved, '03IN24', $userFirstACSubmits, 3, 24 * 60 * 60);
        $this->achievementSpeed($user, $achieved, '05IN24', $userFirstACSubmits, 5, 24 * 60 * 60);
        $this->achievementSpeed($user, $achieved, '10IN24', $userFirstACSubmits, 10, 24 * 60 * 60);

        // Training section achievements
        $this->achievementsTraining($user, $achieved, $userFirstACSubmitPerProblem);

        // Games
        $this->achievementPlayedGame($user, $achieved, 'PLAYED', $userSubmits, 1);
        $this->achievementPlayedGame($user, $achieved, 'GAMERR', $userSubmits, count($this->standings));
        $this->achievementWonGame($user, $achieved, 'WINNER', $userSubmits);

        // TODO: Competitions

        // Unusual dates
        $this->achievementDate($user, $achieved, 'BIRTHD', $userSubmits, date('m-d', strtotime($user->birthdate)));
        $this->achievementDate($user, $achieved, 'CHRSTM', $userSubmits, '12-25');
        $this->achievementDate($user, $achieved, 'NUYEAR', $userSubmits, '01-01');

        // Unusual times
        $this->achievementUnusualTime($user, $achieved, 'NIGHTY', $userSubmits, 2, 6);
        $this->achievementUnusualTime($user, $achieved, 'MORNIN', $userSubmits, 6, 10);

        // Ad-hoc achievements
        $this->achievementMarried($user, $achieved, 'WEDDED');
        $this->achievementRegistered($user, $achieved, 'RGSTRD');
        $this->achievementReported($user, $achieved, 'REPORT', $userReports);
        $this->achievementActive($user, $achieved, 'ACTIVE');
        $this->achievementTested($user, $achieved, 'TESTED', $userSubmits);
        $this->achievementRanked($user, $achieved, 'RANK01', 1);
        $this->achievementRanked($user, $achieved, 'RANK10', 10);
        $this->achievementVirgin($user, $achieved, 'VIRGIN');
        $this->achievementRainbow($user, $achieved, '3LANGS', $userACSubmits);
        $this->achievementOldtimer($user, $achieved, 'OLDREG');
        $this->achievementSoWrong($user, $achieved, 'WARETL', $userSubmits);
        $this->achievementDisaster($user, $achieved, 'HATTRK', $userSubmits, 3);
        $this->achievementDisaster($user, $achieved, 'QUATRO', $userSubmits, 4);
        $this->achievementDisaster($user, $achieved, 'PNTGRM', $userSubmits, 5);
        $this->achievementUnsuccess($user, $achieved, '10FAIL', $userSubmits, 10);
        $this->achievementUnsuccess($user, $achieved, '20FAIL', $userSubmits, 20);
        $this->achievementAccurate($user, $achieved, '20FRST', $userSubmits, 20);
        $this->achievementAccurate($user, $achieved, '50FRST', $userSubmits, 50);
        $this->achievementAccurate($user, $achieved, '100FST', $userSubmits, 100);
        $this->achievementPedantic($user, $achieved, 'TRICKY', $userSubmits);
        // TODO: AVATAR
        $this->achievementProfile($user, $achieved, 'PROFIL');
        $this->achievementReimplemented($user, $achieved, 'ACCAGN', $userACSubmits);
        $this->achievementOffByOne($user, $achieved, 'OFFBY1', $userSubmits);
        $this->achievementDifferentLocations($user, $achieved, '3DIFIP', $userSubmits);

        // Problem-specific achievements
        $this->achievementProblem($user, $achieved, 'SHEEPS', $userACSubmits, 'Sheep');
        $this->achievementProblem($user, $achieved, 'TOWERS', $userACSubmits, 'Radio Towers');
        $this->achievementProblem($user, $achieved, 'DTHSTR', $userACSubmits, 'Deathstars');
        $this->achievementProblem($user, $achieved, 'SNWCLN', $userACSubmits, 'Snow Cleaning');
        $this->achievementProblem($user, $achieved, 'SHADES', $userACSubmits, 'Shades');

        // Submission ID achievements
        $this->achievementSpecialIDSubmit($user, $achieved, 'PRMSUB', $userSubmits, $this->PRIME_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, 'FIBSUB', $userSubmits, $this->FIBONACCI_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, 'PRFSUB', $userSubmits, $this->PERFECT_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, '124SUB', $userSubmits, $this->POWER_OF_TWO_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, '110SUB', $userSubmits, $this->POWER_OF_TEN_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, '314SUB', $userSubmits, $this->PI_PREFIX_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, '271SUB', $userSubmits, $this->E_PREFIX_NUMBERS);

        // Code length achievements
        $this->achievementCodeLength($user, $achieved, 'SHORTY', $userSubmits, -10);
        $this->achievementCodeLength($user, $achieved, 'LONG01', $userSubmits, 100);
        $this->achievementCodeLength($user, $achieved, 'LONG02', $userSubmits, 500);
        $this->achievementCodeLength($user, $achieved, 'LONG03', $userSubmits, 1000);

        // Persistence achievements
        $this->achievementPersistence($user, $achieved, 'PERS01', $userSubmits, 3);
        $this->achievementPersistence($user, $achieved, 'PERS02', $userSubmits, 7);
        $this->achievementPersistence($user, $achieved, 'PERS03', $userSubmits, 30);
    }

    private function recalcAll() {
        $this->brain = new Brain();

        $this->initSpecialNumbers();
        $this->initTrickyProblems();

        $this->users = $this->brain->getAllUsers();
        $this->usersInfo = $this->brain->getAllUsersInfo();
        $this->games = $this->brain->getAllGames();
        $this->problems = $this->brain->getAllProblems();
        $this->ranking = RankingPage::getRanking();
        $this->reports = $this->brain->getReports();
        $this->achievements = $this->brain->getAchievements();
        $this->training = $this->brain->getTrainingTopics();


        // Consider only user submits (exclude system and admin ones)
        $allSubmits = $this->brain->getAllSubmits();
        $allSources = $this->brain->getAllSources();
        $this->submits = array();
        $this->sources = array();
        $submitCount = count($allSubmits);
        for ($i = 0; $i < $submitCount; $i++) {
            if ($allSubmits[$i]['id'] != $allSources[$i]['submitId'])
                error_log('Mismatch in submits and sources at index ' . $i . '!');
            // Ignore system and admin submits
            if ($allSubmits[$i]['userId'] >= 2) {
                array_push($this->submits, $allSubmits[$i]);
                array_push($this->sources, $allSources[$i]);
            }
        }


        $this->standings = array();
        foreach ($this->games as $game) {
            $problem = Problem::instanceFromArray($game);
            if ($problem->type == 'game') {
                $this->standings[$game['id']] = GamesPage::getGameRanking($problem);
            } else if ($problem->type == 'relative' || $problem->type == 'interactive') {
                $this->standings[$game['id']] = GamesPage::getRelativeRanking($problem);
            } else {
                echo 'WARNING: Unknown type of game: ' . $problem->type . PHP_EOL;
                error_log('WARNING: Unknown type of game: ' . $problem->type);
            }
        }

        $this->firstACSubmit = array();
        $submitCount = count($this->submits);
        for ($i = 0; $i < $submitCount; $i++) {
            $submit = $this->submits[$i];
            if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                if (!array_key_exists($submit['problemId'], $this->firstACSubmit))
                    $this->firstACSubmit[$submit['problemId']] = $i;
            }
        }

        // Problems info
        $this->isGame = array();
        foreach ($this->problems as $problem)
            $this->isGame[$problem['id']] = false;
        foreach ($this->games as $game)
            $this->isGame[$game['id']] = true;

        $this->problemTags = array();
        $this->problemTagsCnt = array();
        $this->problemDifficulties = array();
        $this->problemDifficultiesCnt = array();
        foreach ($this->problems as $problem) {
            // Tags
            $tags = explode(',', $problem['tags']);
            $this->problemTags[$problem['id']] = $tags;
            foreach ($tags as $tag) {
                if (!array_key_exists($tag, $this->problemTagsCnt))
                    $this->problemTagsCnt[$tag] = 0;
                $this->problemTagsCnt[$tag]++;
            }

            // Difficulties
            $difficulty = $problem['difficulty'];
            $this->problemDifficulties[$problem['id']] = $difficulty;
            if (!array_key_exists($difficulty, $this->problemDifficultiesCnt))
                $this->problemDifficultiesCnt[$difficulty] = 0;
            $this->problemDifficultiesCnt[$difficulty]++;
        }

        $userSubmits = array();
        $userAchievements = array();
        $userReports = array();
        foreach ($this->users as $user) {
            $userSubmits[$user['id']] = array();
            $userAchievements[$user['id']] = array();
            $userReports[$user['id']] = array();
        }

        // Sent submission IDs on problems (per-user)
        $submitCount = count($this->submits);
        for ($i = 0; $i < $submitCount; $i++) {
            array_push($userSubmits[$this->submits[$i]['userId']], $i);
        }

        // Sent reports (per-user)
        $reportCount = count($this->reports);
        for ($i = 0; $i < $reportCount; $i++) {
            array_push($userReports[$this->reports[$i]['user']], $i);
        }

        // Already achieved (per-user)
        $achievementCount = count($this->achievements);
        for ($i = 0; $i < $achievementCount; $i++) {
            array_push($userAchievements[$this->achievements[$i]['user']], $i);
        }

        // Skip service user and admin
        $userCount = count($this->users);
        for ($i = 2; $i < $userCount; $i++) {
            $user = User::instanceFromArray($this->users[$i], $this->usersInfo[$i]);
            $this->updateAll($user, $userSubmits[$user->id], $userAchievements[$user->id], $userReports[$user->id]);
        }
    }

    private function getAchievementsList($achievementsData) {
        $this->brain = new Brain();

        $userName = array();
        foreach ($this->brain->getAllUsers() as $user)
            $userName[$user['id']] = $user['username'];

        $perType = array();
        foreach ($achievementsData as $achievement)
            $perType[$achievement['key']] = array();

        $achievements = $this->brain->getAchievements();
        foreach ($achievements as $achievement)
            array_push($perType[$achievement['achievement']], $userName[$achievement['user']]);

        $content = '<div>';
        foreach ($achievementsData as $achievement) {
            $users = '';
            for ($i = 0; $i < min(5, count($perType[$achievement['key']])); $i++) {
                if ($i > 0) $users .= ', ';
                $users .= $perType[$achievement['key']][$i];
            }
            $info = $achievement['description'] . PHP_EOL . $users;
            $content .= '
                <div class="achievement" title="' . $info . '">
                    <i class="' . $achievement['icon'] . ' fa-fw"></i>
                    <span>' . $achievement['title'] . ' | ' . count($perType[$achievement['key']]) . '</span>
                </div>
            ';
        }
        $content .= '</div>';
        $content .= '<div style="text-align: center; font-weight: bold;">All achievements: ' . count($achievements) . '</div>';

        return $content;
    }

    public static function getNewAchievements($user) {
        // If not logged in, return no achievements
        if ($user->id == -1)
            return array();

        $brain = new Brain();
        $achievements = $brain->getAchievements($user->id);
        $unseen = array();
        foreach ($achievements as $achievement) {
            if (!$achievement['seen']) {
                array_push($unseen, $achievement['achievement']);
                $brain->markAsSeenAchievement($achievement['id']);
                if (count($unseen) >= 3)
                    break;
            }
        }
        return $unseen;
    }

    public function getContent() {
        $this->brain = new Brain();

        // First load the achievement data
        $achievementsFile = file_get_contents($GLOBALS['PATH_ACHIEVEMENTS'] . '/achievements.json');
        $achievementsData = json_decode($achievementsFile, true);
        foreach ($achievementsData as $achievement) {
            $this->achievementTitle[$achievement['key']] = $achievement['title'];
        }

        $elapsed = '';
        if (isset($_GET['recalc']) && $_GET['recalc'] == 'true') {
            $startTime = microtime(true);
            $this->recalcAll();
            $elapsed = sprintf('<p>Calculated in %.3f seconds.</p>', microtime(true) - $startTime);
        }
        $allAchievements = $this->getAchievementsList($achievementsData);
        $content = inBox('
            <h1>Админ::Постижения</h1>
            ' . $elapsed . '
            <br>
            ' . $allAchievements . '
            <div class="centered">
                <a href="/admin/achievements/recalc">
                    <input type="submit" id="recalc-button" value="Преизчисли" class="button button-color-blue button-large">
                </a>
            </div>
        ');
        return $content;
    }
}

?>
