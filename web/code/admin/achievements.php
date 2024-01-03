<?php
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../page.php");
require_once(__DIR__ . "/../games.php");
require_once(__DIR__ . "/../ranking.php");
require_once(__DIR__ . "/../db/brain.php");
require_once(__DIR__ . "/../entities/user.php");
require_once(__DIR__ . "/../entities/problem.php");


class AdminAchievementsPage extends Page {
    private array $achievementTitle = array();
    private array $PRIME_NUMBERS = array();
    private array $FIBONACCI_NUMBERS = array();
    private array $PERFECT_NUMBERS = array();
    private array $POWER_OF_TWO_NUMBERS = array();
    private array $POWER_OF_TEN_NUMBERS = array();
    private array $PI_PREFIX_NUMBERS = array();
    private array $E_PREFIX_NUMBERS = array();
    private array $TRICKY_PROBLEMS = array();

    /** @var Problem[]|null */
    private ?array $tasks;
    /** @var Problem[]|null */
    private ?array $games;

    private ?array $isGame;
    private ?array $training;
    private ?array $standings;
    private ?array $ranking;
    private ?array $firstACSubmit;
    private ?array $problemTags;
    private ?array $problemTagCount;
    private ?array $taskDifficulties;
    private ?array $taskDifficultyCount;

    public function getTitle(): string {
        return "O(N)::Admin - Achievements";
    }

    public function getExtraScripts(): array {
        return array("/scripts/admin.js");
    }

    public function addAchievement(User $user, array &$achieved, string $key, string $time): void {
//        print("Adding achievement for user {$user->getUsername()}.<br>");
        // Add the achievement to the DB
        Brain::addAchievement($user->getId(), $key, $time);
        // Mark the achievement as achieved for the user
        $achieved[$key] = true;
        // Record the achievement in the logs
        $logMessage = "User {$user->getUsername()} unlocked achievement '{$this->achievementTitle[$key]}'.";
        write_log($GLOBALS["LOG_ACHIEVEMENTS"], $logMessage);
    }


    public function initSpecialNumbers(): void {
        // Prime numbers
        $limit = 100000;
        $isPrime = array_fill(0, $limit, true);
        $isPrime[0] = $isPrime[1] = false;
        for ($num = 2; $num * $num < $limit; $num++) {
            if ($isPrime[$num]) {
                for ($comp = $num * $num; $comp < $limit; $comp += $num)
                    $isPrime[$comp] = false;
            }
        }
        for ($num = 0; $num < $limit; $num++) {
            if ($isPrime[$num]) $this->PRIME_NUMBERS[$num] = true;
        }

        // Fibonacci numbers
        $fibonacciNumbers = array(0, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144, 233, 377,
            610, 987, 1597, 2584, 4181, 6765, 10946, 17711, 28657, 46368, 75025, 121393,
            196418, 317811, 514229, 832040, 1346269, 2178309, 3524578, 5702887, 9227465,
            14930352, 24157817, 39088169, 63245986, 102334155);
        foreach ($fibonacciNumbers as $num) {
            $this->FIBONACCI_NUMBERS[$num] = true;
        }

        // Perfect numbers
        $perfectNumbers = array(6, 28, 496, 8128, 33550336);
        foreach ($perfectNumbers as $num) {
            $this->PERFECT_NUMBERS[$num] = true;
        }

        // Powers of two
        $powersOfTwo = array(1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048, 4096, 8192, 16384, 32768, 65536, 131072,
            262144, 524288, 1048576, 2097152, 4194304, 8388608, 16777216, 33554432, 67108864, 134217728, 268435456);
        foreach ($powersOfTwo as $num) {
            $this->POWER_OF_TWO_NUMBERS[$num] = true;
        }

        // Powers of ten
        $powersOfTen = array(1, 10, 100, 1000, 10000, 100000, 1000000, 10000000, 100000000);
        foreach ($powersOfTen as $num) {
            $this->POWER_OF_TEN_NUMBERS[$num] = true;
        }

        // PI prefixes
        $piPrefixes = array(3, 31, 314, 3141, 31415, 314159, 3141592, 31415926, 314159265);
        foreach ($piPrefixes as $num) {
            $this->PI_PREFIX_NUMBERS[$num] = true;
        }

        // E prefixes
        $ePrefixes = array(2, 27, 271, 2718, 27182, 271828, 2718281, 27182818, 271828182);
        foreach ($ePrefixes as $num) {
            $this->E_PREFIX_NUMBERS[$num] = true;
        }
    }

    public function initTrickyProblems(): void {
        $trickyProblemNames = array(
            "Sheep", "Ssssss", "Bribes", "Sequence Members", "Wordrow", "Next", "Shades", "Seats",
            "Bazinga!", "Crosses", "Collatz", "Passwords", "Digit Holes", "Directory Listing"
        );
        $problems = Problem::getAllTasks();
        foreach ($problems as $problem) {
            if (in_array($problem->getName(), $trickyProblemNames, true))
                $this->TRICKY_PROBLEMS[$problem->getId()] = true;
        }
        if (count($this->TRICKY_PROBLEMS) != count($trickyProblemNames)) {
            echo "WARNING: Couldn't initialize TRICKY_PROBLEMS array properly.";
        }
    }

    // Has submitted or solved X times/problems from a given type (tags, difficulty, or any)
    /** @param Submit[] $userTypeSubmits */
    public function achievementSubmits(User $user, array &$achieved, string $key, array $userTypeSubmits, int $limit): void {
        if (!array_key_exists($key, $achieved)) {
            if (count($userTypeSubmits) >= $limit) {
                $date = $userTypeSubmits[$limit - 1]->getSubmitted();
                $this->addAchievement($user, $achieved, $key, $date);
            }
        }
    }

    // Has solved problems of all tags or difficulties
    /** @param array[] $userSolvedPerDiff */
    public function achievementAllTagsDifficulties(User $user, array &$achieved, string $key, array $userSolvedPerDiff): void {
        if (!array_key_exists($key, $achieved)) {
            $date = "";
            foreach ($userSolvedPerDiff as $submits) {
                if (count($submits) == 0)
                    return;
                $date = max($date, $submits[0]->getSubmitted());
            }
            $this->addAchievement($user, $achieved, $key, $date);
        }
    }

    // Solved X problems in Y minutes
    /** @param Submit[] $userFirstACSubmits */
    public function achievementSpeed(User $user, array &$achieved, string $key, array $userFirstACSubmits, int $count, int $limit): void {
        if (!array_key_exists($key, $achieved)) {
            for ($i = 0; $i + $count <= count($userFirstACSubmits); $i++) {
                $ts1 = strtotime($userFirstACSubmits[$i]->getSubmitted());
                $ts2 = strtotime($userFirstACSubmits[$i + $count - 1]->getSubmitted());
                if ($ts2 - $ts1 <= $limit) {
                    $date = $userFirstACSubmits[$i + $count - 1]->getSubmitted();
                    $this->addAchievement($user, $achieved, $key, $date);
                    return;
                }
            }
        }
    }

    // Solved various training sections
    /** @param Submit[] $userFirstACSubmitPerProblem */
    public function achievementsTraining(User $user, array &$achieved, array $userFirstACSubmitPerProblem): void {
        $completed = 0;
        $latestDate = "";
        foreach ($this->training as $section) {
            $key = "T_{$section['key']}";
            if (!array_key_exists($key, $achieved)) {
                $date = "";
                $sectionProblems = parseStringArray($section["problems"]);
                foreach ($sectionProblems as $problemId) {
                    if (!array_key_exists($problemId, $userFirstACSubmitPerProblem)) {
                        $date = "";
                        break;
                    }
                    $submit = $userFirstACSubmitPerProblem[$problemId];
                    if ($date == "" || $date < $submit->getSubmitted())
                        $date = $submit->getSubmitted();
                }
                if ($date != "") {
                    $this->addAchievement($user, $achieved, $key, $date);
                    $completed++;
                    if ($latestDate == "" || $latestDate < $date)
                        $latestDate = $date;
                }
            } else {
                $completed++;
            }
        }
        if (!array_key_exists("GRADU8", $achieved)) {
            if ($completed == count($this->training)) {
                $this->addAchievement($user, $achieved, "GRADU8", $latestDate);
            }
        }
    }

    // Has played X games
    /** @param Submit[] $userSubmits */
    public function achievementPlayedGame(User $user, array &$achieved, string $key, array $userSubmits, int $limit): void {
        if (!array_key_exists($key, $achieved)) {
            $played = array();
            foreach ($userSubmits as $submit) {
                if ($this->isGame[$submit->getProblemId()]) {
                    if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"]) {
                        if (!array_key_exists($submit->getProblemId(), $played)) {
                            $played[$submit->getProblemId()] = true;
                            if (count($played) >= $limit) {
                                $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                                return;
                            }
                        }
                    }
                }
            }
        }
    }

    // Has won a game
    /** @param Submit[] $userSubmits */
    public function achievementWonGame(User $user, array &$achieved, string $key, array $userSubmits) {
        if (!array_key_exists($key, $achieved)) {
            $date = "";
            foreach ($this->standings as $ranking) {
                if (count($ranking) > 0) {
                    if ($ranking[0]["userId"] == $user->getId()) {
                        foreach ($userSubmits as $submit) {
                            if ($submit->getId() == $ranking[0]["submitId"]) {
                                if ($date == "" || $date > $submit->getSubmitted()) {
                                    $date = $submit->getSubmitted();
                                }
                            }
                        }
                    }
                }
            }
            // Iterate all games in order to find the earliest winning submit
            if ($date != "") {
                $this->addAchievement($user, $achieved, $key, $date);
            }
        }
    }

    // Has married Sasho
    public function achievementMarried(User $user, array &$achieved, string $key): void {
        if (!array_key_exists($key, $achieved)) {
            if ($user->getUsername() == "kopche") {
                $this->addAchievement($user, $achieved, $key, "2018-09-02");
            }
        }
    }

    // Has registered
    public function achievementRegistered(User $user, array &$achieved, string $key): void {
        if (!array_key_exists($key, $achieved)) {
            $this->addAchievement($user, $achieved, $key, $user->getRegistered());
        }
    }

    // Has reported a problem
    public function achievementReported(User $user, array &$achieved, string $key, array $userReports): void {
        if (!array_key_exists($key, $achieved)) {
            if (count($userReports) > 0) {
                $this->addAchievement($user, $achieved, $key, $userReports[0]["date"]);
            }
        }
    }

    // Has over 1000 actions on the site
    public function achievementActive(User $user, array &$achieved, string $key): void {
        if (!array_key_exists($key, $achieved)) {
            if ($user->getActions() >= 1000) {
                $this->addAchievement($user, $achieved, $key, date("Y-m-d H:i:s"));
            }
        }
    }

    // Has ran over 10000 tests
    /** @param Submit[] $userSubmits */
    public function achievementTested(User $user, array &$achieved, string $key, array $userSubmits): void {
        if (!array_key_exists($key, $achieved)) {
            $total = 0;
            foreach ($userSubmits as $submit) {
                $total += count($submit->getResults());
                if ($total >= 10000) {
                    $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                    return;
                }
            }
        }
    }

    // Ranked in top X
    public function achievementRanked(User $user, array &$achieved, string $key, int $limit): void {
        if (!array_key_exists($key, $achieved)) {
            $maxPos = min($limit, count($this->ranking));
            for ($pos = 0; $pos < $maxPos; $pos++) {
                if ($this->ranking[$pos]->getId() == $user->getId()) {
                    $this->addAchievement($user, $achieved, $key, date("Y-m-d H:i:s"));
                    return;
                }
            }
        }
    }

    // Was the first to solve a problem
    public function achievementVirgin(User $user, array &$achieved, string $key): void {
        if (!array_key_exists($key, $achieved)) {
            $date = "";
            // Iterate all problems so we get the earliest virgin submit
            foreach ($this->firstACSubmit as $submit) {
                if ($submit->getUserId() == $user->getId()) {
                    if ($date == "" || $date > $submit->getSubmitted())
                        $date = $submit->getSubmitted();
                }
            }
            if ($date != "") {
                $this->addAchievement($user, $achieved, $key, $date);
            }
        }
    }

    // Submitted a problem in unusual time (late in the night or early in the morning)
    /** @param Submit[] $userSubmits */
    public function achievementUnusualTime(User $user, array &$achieved, string $key, array $userSubmits, int $lower, int $upper): void {
        if (!array_key_exists($key, $achieved)) {
            foreach ($userSubmits as $submit) {
                $hour = date("H", strtotime($submit->getSubmitted()));
                if ($hour >= $lower && $hour < $upper) {
                    $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                    return;
                }
            }
        }
    }

    // Solved problems in 3 different languages
    /** @param Submit[] $userACSubmits */
    public function achievementRainbow(User $user, array &$achieved, string $key, array $userACSubmits): void {
        if (!array_key_exists($key, $achieved)) {
            $languages = array();
            foreach ($userACSubmits as $submit) {
                if (!array_key_exists($submit->getLanguage(), $languages)) {
                    $languages[$submit->getLanguage()] = true;
                    if (count($languages) >= 3) {
                        $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                        return;
                    }
                }
            }
        }
    }

    // Registered more than a year ago
    public function achievementOldtimer(User $user, array &$achieved, string $key): void {
        if (!array_key_exists($key, $achieved)) {
            $anniversary = strtotime($user->getRegistered()) + 365 * 24 * 60 * 60;
            if (time() >= $anniversary) {
                $date = date("Y-m-d", $anniversary);
                $this->addAchievement($user, $achieved, $key, $date);
            }
        }
    }

    // Submit on Christmas, New Year, or user's birthday
    /** @param Submit[] $userSubmits */
    public function achievementDate(User $user, array &$achieved, string $key, array $userSubmits, string $target): void {
        if (!array_key_exists($key, $achieved)) {
            foreach ($userSubmits as $submit) {
                if (date("m-d", strtotime($submit->getSubmitted())) == $target) {
                    $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                    return;
                }
            }
        }
    }

    private function getStatusBit(?string $status): int {
        $bits = array(
            $GLOBALS["STATUS_ACCEPTED"] => (1 << 0),
            $GLOBALS["STATUS_WRONG_ANSWER"] => (1 << 1),
            $GLOBALS["STATUS_RUNTIME_ERROR"] => (1 << 2),
            $GLOBALS["STATUS_TIME_LIMIT"] => (1 << 3),
            $GLOBALS["STATUS_COMPILATION_ERROR"] => (1 << 4),
            $GLOBALS["STATUS_MEMORY_LIMIT"] => (1 << 5),
            $GLOBALS["STATUS_INTERNAL_ERROR"] => (1 << 6)
        );
        return array_key_exists($status, $bits) ? $bits[$status] : 0;
    }

    // Got three different types of errors on a problem
    /** @param Submit[] $userSubmits */
    public function achievementSoWrong(User $user, array &$achieved, string $key, array $userSubmits): void {
        if (!array_key_exists($key, $achieved)) {
            $errorMasks = array();
            foreach ($userSubmits as $submit) {
                if (!array_key_exists($submit->getProblemId(), $errorMasks))
                    $errorMasks[$submit->getProblemId()] = 0;
                $errorMasks[$submit->getProblemId()] |= $this->getStatusBit($submit->getStatus());

                if (popcount($errorMasks[$submit->getProblemId()]) >= 3) {
                    $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                    return;
                }
            }
        }
    }

    // Got several different types of errors in a single submit
    /** @param Submit[] $userSubmits */
    public function achievementDisaster(User $user, array &$achieved, string $key, array $userSubmits, int $limit): void {
        if (!array_key_exists($key, $achieved)) {
            foreach ($userSubmits as $submit) {
                $errorMask = 0;
                foreach($submit->getResults() as $result) {
                    $errorMask |= is_numeric($result) ? (1 << 0) : $this->getStatusBit($result);
                }
                if (popcount($errorMask) >= $limit) {
                    $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                    return;
                }
            }
        }
    }

    // X unsuccessful submits on a problem
    /** @param Submit[] $userSubmits */
    public function achievementUnsuccess(User $user, array &$achieved, string $key, array $userSubmits, int $target): void {
        if (!array_key_exists($key, $achieved)) {
            $unsuccessful = array();
            foreach ($userSubmits as $submit) {
                if (!$this->isGame[$submit->getProblemId()]) {
                    if (!array_key_exists($submit->getProblemId(), $unsuccessful))
                        $unsuccessful[$submit->getProblemId()] = 0;
                    if ($submit->getStatus() != $GLOBALS["STATUS_ACCEPTED"])
                        $unsuccessful[$submit->getProblemId()]++;
                    if ($unsuccessful[$submit->getProblemId()] >= $target) {
                        $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                        return;
                    }
                }
            }
        }
    }

    // Solved X tasks on the first try
    /** @param Submit[] $userSubmits */
    public function achievementAccurate(User $user, array &$achieved, string $key, array $userSubmits, int $target): void {
        if (!array_key_exists($key, $achieved)) {
            $onFirstTry = 0;
            $submitted = array();
            foreach ($userSubmits as $submit) {
                if (!array_key_exists($submit->getProblemId(), $submitted)) {
                    $submitted[$submit->getProblemId()] = true;
                    if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"]) {
                        $onFirstTry++;
                        if ($onFirstTry >= $target) {
                            $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                            return;
                        }
                    }
                }
            }
        }
    }

    // Solved 5 tricky problems on the first try
    /** @param Submit[] $userSubmits */
    public function achievementPedantic(User $user, array &$achieved, string $key, array $userSubmits): void {
        if (!array_key_exists($key, $achieved)) {
            $onFirstTry = 0;
            $submitted = array();
            foreach ($userSubmits as $submit) {
                if (!array_key_exists($submit->getProblemId(), $submitted)) {
                    $submitted[$submit->getProblemId()] = true;
                    if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"]) {
                        if (array_key_exists($submit->getProblemId(), $this->TRICKY_PROBLEMS)) {
                            $onFirstTry++;
                            if ($onFirstTry >= 5) {
                                $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                                return;
                            }
                        }
                    }
                }
            }
        }
    }

    // Sent a submission year or more after the latest submission
    /** @param Submit[] $userSubmits */
    public function achievementWelcomeBack(User $user, array &$achieved, string $key, array $userSubmits): void {
        if (!array_key_exists($key, $achieved)) {
            $lastSubmit = null;
            foreach ($userSubmits as $submit) {
                $currSubmit = new DateTime($submit->getSubmitted());
                if ($lastSubmit != null) {
                    if ($currSubmit->diff($lastSubmit)->format("%y") > "00") {
                        $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                        return;
                    }
                }
                $lastSubmit = $currSubmit;
            }
        }
    }

    // Filled all profile information
    public function achievementProfile(User $user, array &$achieved, string $key) {
        if (!array_key_exists($key, $achieved)) {
            if ($user->getEmail() && $user->getTown() && $user->getCountry() && $user->getGender() && $user->getBirthdate() != "0000-00-00") {
                // TODO: Set the achievement date to the one this actually happened once info edit is available
                $this->addAchievement($user, $achieved, $key, $user->getRegistered());
            }
        }
    }

    // Solved again an already accepted task with a new solution
    /** @param Submit[] $userACSubmits */
    public function achievementReimplemented(User $user, array &$achieved, string $key, array $userACSubmits): void {
        if (!array_key_exists($key, $achieved)) {
            $prevSource = array();
            foreach ($userACSubmits as $submit) {
                if (array_key_exists($submit->getProblemId(), $prevSource)) {
                    if ($prevSource[$submit->getProblemId()] != $submit->getSource()) {
                        $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                        return;
                    }
                }
                $prevSource[$submit->getProblemId()] = $submit->getSource();
            }
        }
    }

    private function isOffByOne(string $str1, string $str2): bool {
//        $str1 = preg_replace("/\s/", "", $str1);
//        $str2 = preg_replace("/\s/", "", $str2);
        if ($str1 == $str2)
            return false;

        $len1 = strlen($str1);
        $len2 = strlen($str2);
        if (abs($len1 - $len2) > 1)
            return false;

        $idx1 = $idx2 = 0;
        while ($idx1 < $len1 && $idx2 < $len2 && $str1[$idx1] == $str2[$idx2]) {
            $idx1++;
            $idx2++;
        }
        if ($idx1 >= $len1 && $idx2 + 1 == $len2)
            return true;
        if ($idx2 >= $len2 && $idx1 + 1 == $len1)
            return true;
        if ($idx1 >= $len1 || $idx2 >= $len2)
            return false;

        if ($len1 == $len2) {
            $idx1++;
            $idx2++;
        } else if ($len1 > $len2) {
            $idx1++;
        } else {
            $idx2++;
        }

        while ($idx1 < $len1 && $idx2 < $len2 && $str1[$idx1] == $str2[$idx2]) {
            $idx1++;
            $idx2++;
        }
        return $idx1 >= $len1 && $idx2 >= $len2;
    }

    // Fixed an off-by-one error to get a problem accepted
    /** @param Submit[] $userSubmits */
    public function achievementOffByOne(User $user, array &$achieved, string $key, array $userSubmits): void {
        if (!array_key_exists($key, $achieved)) {
            $prevProblemSubmit = array();
            foreach ($userSubmits as $currSubmit) {
                if (array_key_exists($currSubmit->getProblemId(), $prevProblemSubmit)) {
                    $prevSubmit = $prevProblemSubmit[$currSubmit->getProblemId()];
                    # We want this submit to be AC and the previous one to be WA/TL/ML
                    if ($currSubmit->getStatus() == $GLOBALS["STATUS_ACCEPTED"] &&
                        $prevSubmit->getStatus() != $GLOBALS["STATUS_ACCEPTED"] &&
                        $prevSubmit->getStatus() != $GLOBALS["STATUS_COMPILATION_ERROR"]) {
                        if ($this->isOffByOne($currSubmit->getSource(), $prevSubmit->getSource())) {
                            $this->addAchievement($user, $achieved, $key, $currSubmit->getSubmitted());
                            return;
                        }
                    }
                }
                $prevProblemSubmit[$currSubmit->getProblemId()] = $currSubmit;
            }
        }
    }

    // Sent solutions from 3 different IPs (hopefully locations, but not necessarily)
    /** @param Submit[] $userSubmits */
    public function achievementDifferentLocations(User $user, array &$achieved, string $key, array $userSubmits): void {
        if (!array_key_exists($key, $achieved)) {
            $ips = array();
            foreach ($userSubmits as $submit) {
                if ($submit->getIp() && !array_key_exists($submit->getIp(), $ips)) {
                    $ips[$submit->getIp()] = true;
                    if (count($ips) >= 3) {
                        $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                        return;
                    }
                }
            }
        }
    }

    // Solved a specific problem
    /** @param Submit[] $userACSubmits */
    public function achievementProblem(User $user, array &$achieved, string $key, array $userACSubmits, int $problemId): void {
        if (!array_key_exists($key, $achieved)) {
            foreach ($userACSubmits as $submit) {
                if ($submit->getProblemId() == $problemId) {
                    $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                    return;
                }
            }
        }
    }

    /** @param Submit[] $userSubmits */
    public function achievementSpecialIDSubmit(User $user, array &$achieved, string $key, array $userSubmits, array $special): void {
        if (!array_key_exists($key, $achieved)) {
            foreach ($userSubmits as $submit) {
                if (array_key_exists($submit->getId(), $special)) {
                    $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                    return;
                }
            }
        }
    }

    /** @param Submit[] $userSubmits */
    public function achievementCodeLength(User $user, array &$achieved, string $key, array $userSubmits, int $lenLimit): void {
        if (!array_key_exists($key, $achieved)) {
            foreach ($userSubmits as $submit) {
                // Don't use userACSubmits as they don't include submits on games
                if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"]) {
                    $length = substr_count($submit->getSource(), "\n") + 1;
                    if (($lenLimit < 0 && $length <= -$lenLimit) || ($lenLimit > 0 && $length >= $lenLimit)) {
                        $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                        return;
                    }
                }
            }
        }
    }

    /** @param Submit[] $userSubmits */
    public function achievementPersistence(User $user, array &$achieved, string $key, array $userSubmits, int $dayLimit): void {
        if (!array_key_exists($key, $achieved)) {
            $consecutive = 1;
            $previous = (int)(strtotime("1970-01-01 00:00:00") / (24 * 60 * 60));
            foreach ($userSubmits as $submit) {
                $current = (int)(strtotime($submit->getSubmitted()) / (24 * 60 * 60));
                if ($current - $previous == 1) {
                    $consecutive++;
                    if ($consecutive >= $dayLimit) {
                        $this->addAchievement($user, $achieved, $key, $submit->getSubmitted());
                        return;
                    }
                } else if ($current - $previous > 1) {
                    $consecutive = 1;
                }
                $previous = $current;
            }
        }
    }

    /** @param Submit[] $userSubmits */
    public function updateAll(User $user, array $userSubmits, array $userAchievements, array $userReports): void {
        // Already achieved
        $achieved = array();
        foreach ($userAchievements as $achievement) {
            $achieved[$achievement["achievement"]] = true;
        }
//        print("User {$user->getUsername()} has " . count($userSubmits) . " submits and " . count($achieved) . " already done achievements.<br>" . PHP_EOL);

        // Solved problems
        $userACSubmits = array();
        $userFirstACSubmits = array();
        $userFirstACSubmitPerProblem = array();
        foreach ($userSubmits as $submit) {
            // Skip games in this list
            if ($this->isGame[$submit->getProblemId()])
                continue;
            if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"]) {
                array_push($userACSubmits, $submit);
                if (!array_key_exists($submit->getProblemId(), $userFirstACSubmitPerProblem)) {
                    $userFirstACSubmitPerProblem[$submit->getProblemId()] = $submit;
                    array_push($userFirstACSubmits, $submit);
                }
            }
        }

        // Solved per problem difficulty and tags
        $userSolvedPerTag = array();
        foreach ($this->problemTagCount as $tag => $cnt)
            $userSolvedPerTag[$tag] = array();

        $userSolvedPerDiff = array();
        foreach ($this->taskDifficultyCount as $difficulty => $cnt)
            $userSolvedPerDiff[$difficulty] = array();

        foreach ($userFirstACSubmits as $submit) {
            // Tags
            foreach ($this->problemTags[$submit->getProblemId()] as $tag)
                array_push($userSolvedPerTag[$tag], $submit);
            // Difficulty
            if (!$this->isGame[$submit->getProblemId()]) {
                $difficulty = $this->taskDifficulties[$submit->getProblemId()];
                array_push($userSolvedPerDiff[$difficulty], $submit);
            }
        }

        // NOTE: Achievements that cannot be recalculated accurately (date is lost):
        //       ACTIVE, RANK01, RANK10
        // EDIT: Actually, can be re-calculated by simulating the submits one by one, but may be slow.

        // Number of submits and solutions achievements
        $this->achievementSubmits($user, $achieved, "SUB1E0", $userSubmits, 1);
        $this->achievementSubmits($user, $achieved, "SUB1E1", $userSubmits, 10);
        $this->achievementSubmits($user, $achieved, "SUB1E2", $userSubmits, 100);
        $this->achievementSubmits($user, $achieved, "SUB1E3", $userSubmits, 1000);
        $this->achievementSubmits($user, $achieved, "SOL1E0", $userFirstACSubmits, 1);
        $this->achievementSubmits($user, $achieved, "SOL1E1", $userFirstACSubmits, 10);
        $this->achievementSubmits($user, $achieved, "SOL1E2", $userFirstACSubmits, 100);
        $this->achievementSubmits($user, $achieved, "ALLTSK", $userFirstACSubmits, count($this->tasks) + count($this->games));

        // Problems difficulty achievements
        $this->achievementSubmits($user, $achieved, "1TRIVL", $userSolvedPerDiff["trivial"], 1);
        $this->achievementSubmits($user, $achieved, "1EASYY", $userSolvedPerDiff["easy"], 1);
        $this->achievementSubmits($user, $achieved, "1MEDIU", $userSolvedPerDiff["medium"], 1);
        $this->achievementSubmits($user, $achieved, "1HARDD", $userSolvedPerDiff["hard"], 1);
        $this->achievementSubmits($user, $achieved, "1BRUTL", $userSolvedPerDiff["brutal"], 1);

        $this->achievementSubmits($user, $achieved, "ALLTRV", $userSolvedPerDiff["trivial"], $this->taskDifficultyCount["trivial"]);
        $this->achievementSubmits($user, $achieved, "ALLESY", $userSolvedPerDiff["easy"], $this->taskDifficultyCount["easy"]);
        $this->achievementSubmits($user, $achieved, "ALLMED", $userSolvedPerDiff["medium"], $this->taskDifficultyCount["medium"]);
        $this->achievementSubmits($user, $achieved, "ALLHRD", $userSolvedPerDiff["hard"], $this->taskDifficultyCount["hard"]);
        $this->achievementSubmits($user, $achieved, "ALLBRL", $userSolvedPerDiff["brutal"], $this->taskDifficultyCount["brutal"]);
        $this->achievementAllTagsDifficulties($user, $achieved, "ALLDIF", $userSolvedPerDiff);

        // Problems tags achievements
        $this->achievementSubmits($user, $achieved, "ALLIMP", $userSolvedPerTag["implement"], $this->problemTagCount["implement"]);
        $this->achievementSubmits($user, $achieved, "ALLSRC", $userSolvedPerTag["search"], $this->problemTagCount["search"]);
        $this->achievementSubmits($user, $achieved, "ALLDPR", $userSolvedPerTag["dp"], $this->problemTagCount["dp"]);
        $this->achievementSubmits($user, $achieved, "ALLGRF", $userSolvedPerTag["graph"], $this->problemTagCount["graph"]);
        $this->achievementSubmits($user, $achieved, "ALLMAT", $userSolvedPerTag["math"], $this->problemTagCount["math"]);
        $this->achievementSubmits($user, $achieved, "ALLGEO", $userSolvedPerTag["geometry"], $this->problemTagCount["geometry"]);
        $this->achievementSubmits($user, $achieved, "ALLADH", $userSolvedPerTag["ad-hoc"], $this->problemTagCount["ad-hoc"]);
        $this->achievementSubmits($user, $achieved, "ALLFLW", $userSolvedPerTag["flow"], $this->problemTagCount["flow"]);
        $this->achievementSubmits($user, $achieved, "ALLDAC", $userSolvedPerTag["divconq"], $this->problemTagCount["divconq"]);
        $this->achievementSubmits($user, $achieved, "ALLSTR", $userSolvedPerTag["strings"], $this->problemTagCount["strings"]);
        $this->achievementSubmits($user, $achieved, "ALLSOR", $userSolvedPerTag["sorting"], $this->problemTagCount["sorting"]);
        $this->achievementSubmits($user, $achieved, "ALLGRD", $userSolvedPerTag["greedy"], $this->problemTagCount["greedy"]);
        $this->achievementSubmits($user, $achieved, "ALLGAM", $userSolvedPerTag["game"], $this->problemTagCount["game"]);
        $this->achievementSubmits($user, $achieved, "ALLDST", $userSolvedPerTag["datastruct"], $this->problemTagCount["datastruct"]);
        $this->achievementSubmits($user, $achieved, "ALLPNP", $userSolvedPerTag["np"], $this->problemTagCount["np"]);
        $this->achievementAllTagsDifficulties($user, $achieved, "ALLTAG", $userSolvedPerTag);

        // Solving speed achievements
        $this->achievementSpeed($user, $achieved, "TWOTEN", $userFirstACSubmits, 2, 10 * 60);
        $this->achievementSpeed($user, $achieved, "03IN24", $userFirstACSubmits, 3, 24 * 60 * 60);
        $this->achievementSpeed($user, $achieved, "05IN24", $userFirstACSubmits, 5, 24 * 60 * 60);
        $this->achievementSpeed($user, $achieved, "10IN24", $userFirstACSubmits, 10, 24 * 60 * 60);

        // Training section achievements
        $this->achievementsTraining($user, $achieved, $userFirstACSubmitPerProblem);

        // Games
        $this->achievementPlayedGame($user, $achieved, "PLAYED", $userSubmits, 1);
        $this->achievementPlayedGame($user, $achieved, "GAMERR", $userSubmits, count($this->standings));
        $this->achievementWonGame($user, $achieved, "WINNER", $userSubmits);

        // Unusual dates
        $this->achievementDate($user, $achieved, "BIRTHD", $userSubmits, date("m-d", strtotime($user->getBirthdate())));
        $this->achievementDate($user, $achieved, "CHRSTM", $userSubmits, "12-25");
        $this->achievementDate($user, $achieved, "NUYEAR", $userSubmits, "01-01");

        // Unusual times
        $this->achievementUnusualTime($user, $achieved, "NIGHTY", $userSubmits, 2, 6);
        $this->achievementUnusualTime($user, $achieved, "MORNIN", $userSubmits, 6, 10);

        // Ad-hoc achievements
        $this->achievementMarried($user, $achieved, "WEDDED");
        $this->achievementRegistered($user, $achieved, "RGSTRD");
        $this->achievementReported($user, $achieved, "REPORT", $userReports);
        $this->achievementActive($user, $achieved, "ACTIVE");
        $this->achievementTested($user, $achieved, "TESTED", $userSubmits);
        $this->achievementRanked($user, $achieved, "RANK01", 1);
        $this->achievementRanked($user, $achieved, "RANK10", 10);
        $this->achievementVirgin($user, $achieved, "VIRGIN");
        $this->achievementRainbow($user, $achieved, "3LANGS", $userACSubmits);
        $this->achievementOldtimer($user, $achieved, "OLDREG");
        $this->achievementSoWrong($user, $achieved, "WARETL", $userSubmits);
        $this->achievementDisaster($user, $achieved, "HATTRK", $userSubmits, 3);
        $this->achievementDisaster($user, $achieved, "QUATRO", $userSubmits, 4);
        $this->achievementDisaster($user, $achieved, "PNTGRM", $userSubmits, 5);
        $this->achievementUnsuccess($user, $achieved, "10FAIL", $userSubmits, 10);
        $this->achievementUnsuccess($user, $achieved, "20FAIL", $userSubmits, 20);
        $this->achievementAccurate($user, $achieved, "20FRST", $userSubmits, 20);
        $this->achievementAccurate($user, $achieved, "50FRST", $userSubmits, 50);
        $this->achievementAccurate($user, $achieved, "100FST", $userSubmits, 100);
        $this->achievementPedantic($user, $achieved, "TRICKY", $userSubmits);
        $this->achievementProfile($user, $achieved, "PROFIL");
        $this->achievementReimplemented($user, $achieved, "ACCAGN", $userACSubmits);
        $this->achievementOffByOne($user, $achieved, "OFFBY1", $userSubmits);
        $this->achievementDifferentLocations($user, $achieved, "3DIFIP", $userSubmits);
        $this->achievementWelcomeBack($user, $achieved, "WLCMBK", $userSubmits);

        // Problem-specific achievements
        $problemIds = array();
        foreach ($this->tasks as $task) {
            $problemIds[$task->getName()] = $task->getId();
        }
        $this->achievementProblem($user, $achieved, "SHEEPS", $userACSubmits, $problemIds["Sheep"]);
        $this->achievementProblem($user, $achieved, "TOWERS", $userACSubmits, $problemIds["Radio Towers"]);
        $this->achievementProblem($user, $achieved, "DTHSTR", $userACSubmits, $problemIds["Deathstars"]);
        $this->achievementProblem($user, $achieved, "SNWCLN", $userACSubmits, $problemIds["Snow Cleaning"]);
        $this->achievementProblem($user, $achieved, "SHADES", $userACSubmits, $problemIds["Shades"]);

        // Submission ID achievements
        $this->achievementSpecialIDSubmit($user, $achieved, "PRMSUB", $userSubmits, $this->PRIME_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, "FIBSUB", $userSubmits, $this->FIBONACCI_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, "PRFSUB", $userSubmits, $this->PERFECT_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, "124SUB", $userSubmits, $this->POWER_OF_TWO_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, "110SUB", $userSubmits, $this->POWER_OF_TEN_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, "314SUB", $userSubmits, $this->PI_PREFIX_NUMBERS);
        $this->achievementSpecialIDSubmit($user, $achieved, "271SUB", $userSubmits, $this->E_PREFIX_NUMBERS);

        // Code length achievements
        $this->achievementCodeLength($user, $achieved, "SHORTY", $userSubmits, -10);
        $this->achievementCodeLength($user, $achieved, "LONG01", $userSubmits, 100);
        $this->achievementCodeLength($user, $achieved, "LONG02", $userSubmits, 500);
        $this->achievementCodeLength($user, $achieved, "LONG03", $userSubmits, 1000);

        // Persistence achievements
        $this->achievementPersistence($user, $achieved, "PERS01", $userSubmits, 3);
        $this->achievementPersistence($user, $achieved, "PERS02", $userSubmits, 7);
        $this->achievementPersistence($user, $achieved, "PERS03", $userSubmits, 30);
    }

    private function initVariables(): void {
        $this->initSpecialNumbers();
        $this->initTrickyProblems();

        $this->tasks = Problem::getAllTasks();
        $this->games = Problem::getAllGames();
        $this->ranking = RankingPage::getRanking();
        $this->training = Brain::getTrainingTopics();

        // Problems info
        $this->isGame = array();
        foreach ($this->games as $game)
            $this->isGame[$game->getId()] = true;
        foreach ($this->tasks as $task)
            $this->isGame[$task->getId()] = false;

        // Standings info
        $this->standings = array();
        foreach ($this->games as $game) {
            if ($game->getType() == "game") {
                $this->standings[$game->getId()] = GamesPage::getGameRanking($game);
            } else if ($game->getType() == "relative" || $game->getType() == "interactive") {
                $this->standings[$game->getId()] = GamesPage::getRelativeRanking($game);
            } else {
                error_log("WARNING: Unknown type of game: {$game->getType()}!");
            }
        }

        // Tags (include both tasks and games (all problems))
        $this->problemTags = array();
        $this->problemTagCount = array();
        foreach (array_merge($this->tasks, $this->games) as $problem) {
            $this->problemTags[$problem->getId()] = $problem->getTags();
            foreach ($problem->getTags() as $tag) {
                if (!array_key_exists($tag, $this->problemTagCount))
                    $this->problemTagCount[$tag] = 0;
                $this->problemTagCount[$tag]++;
            }
        }

        // Difficulties (include only tasks)
        $this->taskDifficulties = array();
        $this->taskDifficultyCount = array();
        foreach ($this->tasks as $task) {
            $difficulty = $task->getDifficulty();
            $this->taskDifficulties[$task->getId()] = $difficulty;
            if (!array_key_exists($difficulty, $this->taskDifficultyCount))
                $this->taskDifficultyCount[$difficulty] = 0;
            $this->taskDifficultyCount[$difficulty]++;
        }

        $this->firstACSubmit = array();
        foreach (Submit::getFirstACSubmits() as $submit) {
            $this->firstACSubmit[$submit->getProblemId()] = $submit;
        }
   }

    // TODO: Invoke on major user actions (e.g., submit, or action % 100 == 0)
    public function recalcUser(User $user): void {
        // Skip service user and admin
        if ($user->getId() <= 1)
            return;

        $this->initVariables();
        $userSubmits = Submit::getUserSubmits($user->getId(), true);
        $userAchievements = Brain::getAchievements($user->getId());
        $userReports = Brain::getReports($user->getId());

        $this->updateAll($user, $userSubmits, $userAchievements, $userReports);
    }

    private function recalcAll(): void {
        $this->initVariables();

        $submits = Submit::getAllSubmits(-1, -1, "all", false);
        // Consider only user submits (exclude system and admin ones)
        $submits = array_filter($submits, function($submit) {return $submit->getUserId() > 1;});

        $users = User::getAllUsers();

        $userSubmits = array();
        $userAchievements = array();
        $userReports = array();

        foreach ($users as $user) {
            $userSubmits[$user->getId()] = array();
            $userAchievements[$user->getId()] = array();
            $userReports[$user->getId()] = array();
        }

        // Sent submissions (per-user)
        foreach ($submits as $submit) {
            array_push($userSubmits[$submit->getUserId()], $submit);
        }

        // Sent reports (per-user)
        foreach (Brain::getReports() as $report) {
            array_push($userReports[$report["userId"]], $report);
        }

        // Already achieved (per-user)
        foreach (Brain::getAchievements() as $achievement) {
            array_push($userAchievements[$achievement["userId"]], $achievement);
        }

        foreach ($users as $user) {
            // Skip system user and admin
            if ($user->getId() <= 1)
                continue;

            $this->updateAll(
                $user,
                $userSubmits[$user->getId()],
                $userAchievements[$user->getId()],
                $userReports[$user->getId()]
            );
        }
    }

    private function getAchievementsList(array $achievementsData): string {
        // NOTE: achievementData is the data stored in the achievements.json
        //       The main difference is that the "achievement" field is called "key" there.
        $userName = array();
        foreach (User::getAllUsers() as $user)
            $userName[$user->getId()] = $user->getUsername();

        $perType = array();
        foreach ($achievementsData as $achievement)
            $perType[$achievement["key"]] = array();

        $numAchievements = 0;
        foreach (Brain::getAchievements() as $achievement) {
            $numAchievements++;
            array_push($perType[$achievement["achievement"]], $userName[$achievement["userId"]]);
        }

        $achievementInfo = "";
        foreach ($achievementsData as $achievement) {
            $users = "";
            $achievementCount = count($perType[$achievement["key"]]);
            for ($i = 0; $i < min(5, $achievementCount); $i++) {
                if ($i > 0) $users .= ", ";
                $users .= $perType[$achievement["key"]][$i];
            }
            $info = $achievement["description"] . PHP_EOL . $users;
            $achievementInfo .= "
                <span class='tooltip--top' data-tooltip='{$info}'>
                    <div class='achievement'>
                        <i class='{$achievement['icon']} fa-fw'></i>
                        <span>{$achievement['title']} | {$achievementCount}</span>
                    </div>
                </span>
            ";
        }
        return "
            <div>
                {$achievementInfo}
            </div>
            <div style='text-align: center; font-weight: bold;'>
                All achievements: {$numAchievements}
            </div>
        ";
    }

    public static function getNewAchievements(User $user): array {
        // If not logged in, return no achievements
        if ($user->getId() == -1)
            return array();

        $achievements = Brain::getAchievements($user->getId());
        $unseen = array();
        foreach ($achievements as $achievement) {
            if (!$achievement["seen"]) {
                array_push($unseen, $achievement["achievement"]);
                Brain::markAsSeenAchievement($achievement["id"]);
                if (count($unseen) >= 3)
                    break;
            }
        }
        return $unseen;
    }

    public function getContent(): string {
        // First load the achievement data
        $achievementsFile = file_get_contents("{$GLOBALS['PATH_DATA']}/achievements/achievements.json");
        $achievementsData = json_decode($achievementsFile, true);
        foreach ($achievementsData as $achievement) {
            $this->achievementTitle[$achievement["key"]] = $achievement["title"];
        }

        $elapsed = "";
        if (isset($_GET["recalc"]) && $_GET["recalc"] == "true") {
            $startTime = microtime(true);
            $this->recalcAll();
            $elapsed = sprintf("Calculated in %.3f seconds.", microtime(true) - $startTime);
            redirect("/admin/achievements", "INFO", $elapsed);
        }
        $allAchievements = $this->getAchievementsList($achievementsData);
        return inBox("
            <h1>Админ::Постижения</h1>
            {$elapsed}
            <br>
            {$allAchievements}
            <div class='centered'>
                <a href='/admin/achievements/recalc'>
                    <input type='submit' id='recalc-button' value='Преизчисли' class='button button-color-blue button-large'>
                </a>
            </div>
        ");
    }
}

?>
