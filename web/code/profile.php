<?php
require_once("db/brain.php");
require_once("common.php");
require_once("page.php");
require_once("stats.php");
require_once("ranking.php");

class ProfilePage extends Page {
    private User $profile;
    /** @var Submit[] */
    private array $submits;

    public function getTitle(): string {
        return "O(N)::{$this->profile->getUsername()}";
    }

    public function getExtraStyles(): array {
        return array("/styles/tooltips.css");
    }

    public function getExtraScripts(): array {
        return array(
            "https://www.gstatic.com/charts/loader.js",
            "/scripts/d3.min.js",
            "/scripts/radarChart.js"
        );
    }

    public function init(): void {
        if (!isset($_GET["user"])) {
            header("Location: /error");
            exit();
        }
        $profile = User::getByUsername($_GET["user"]);
        if ($profile === null) {
            header("Location: /error");
            exit();
        } else {
            $this->profile = $profile;
            $this->submits = Submit::getUserSubmits($profile->getId());
        }
    }

    private function getPercentage(array $total, array $solved, string $tag): float {
        if ($total[$tag] == 0)
            return 1.0;
        return $solved[$tag] / $total[$tag];
    }

    private function skillRadarChart(): string {
        // Radar chart library taken from: http://www.visualcinnamon.com/2015/10/different-look-d3-radar-chart.html

        $content = "
             <div class='radarChart' style='float: right; background-color: #FFFFFF; margin-right: -40px;'></div>
        ";

        // Evaluate how good the users is at each topic (tag).
        $totalTags = array();
        $solvedTags = array();
        foreach ($GLOBALS["PROBLEM_TAGS"] as $tag => $name) {
            $totalTags[$tag] = 0;
            $solvedTags[$tag] = 0;
        }

        $problems = Problem::getAllTasks();
        $solved = Brain::getSolved($this->profile->getId());

        foreach ($problems as $problem) {
            foreach ($problem->getTags() as $tag) if ($tag != "") {
                $totalTags[$tag]++;
                if (in_array($problem->getId(), $solved)) {
                    $solvedTags[$tag]++;
                }
            }
        }

        // Prepare the data for the radar chart
        $skillsData = array();

        // Current user
        $skills = array();
        $remain = 12; // Limit to 12 so the chart doesn't become overcrowded
        foreach ($GLOBALS["PROBLEM_TAGS"] as $tag => $name) {
            if ($remain >= 0) {
                $remain--;
                array_push($skills,
                    array("axis" => $name, "value" => $this->getPercentage($totalTags, $solvedTags, $tag))
                );
            }
        }
        array_push($skillsData, $skills);

        // Invoke the JS code to generate the radar chart
        $content .= "
            <script>
                let data = " . json_encode($skillsData) . ";
                let options = {
                    w: 200,
                    h: 200,
                    margin: {top: 40, right: 55, bottom: 40, left: 55},
                    maxValue: 1.0,
                    levels: 5,
                    roundStrokes: false,
                    color: d3.scale.ordinal().range(['#0099FF', '#DD4337', '#00A0B0', '#CC333F', '#EDC951'])
                };
                RadarChart('.radarChart', data, options);
            </script>
        ";
        return $content;
    }

    private function submissionDotChart(): string {
        $submits = $this->submits;
        if (count($submits) == 0)
            return "";

        // Submits dot chart
        $dots = "";
        foreach ($submits as $submit) {
            switch ($submit->getStatus()) {
                case $GLOBALS["STATUS_ACCEPTED"]:
                    $color = "green";
                    break;
                case $GLOBALS["STATUS_WRONG_ANSWER"]:
                    $color = "red";
                    break;
                case $GLOBALS["STATUS_TIME_LIMIT"]:
                    $color = "yellow";
                    break;
                case $GLOBALS["STATUS_MEMORY_LIMIT"]:
                    $color = "dull-purple";
                    break;
                case $GLOBALS["STATUS_RUNTIME_ERROR"]:
                    $color = "asphalt";
                    break;
                case $GLOBALS["STATUS_COMPILATION_ERROR"]:
                    $color = "blue";
                    break;
                case $GLOBALS["STATUS_INTERNAL_ERROR"]:
                    $color = "black";
                    break;
                default:
                    $color = "gray";
            }
            $dots .= "<div class='profile-dot background-{$color}' title='{$submit->getStatus()}'></div>";
        }

        return "
            <h2>Предадени Решения</h2>
            <div class='profile-dot-box'>{$dots}</div>
            <br>
        ";
    }

    private function submissionActivity(): string {
        $submits = $this->submits;
        $NUM_INTERVALS = 16;

        // Submits activity over time
        $lastDate = time();
        // Make the interval be at least one year (useful for recently registered users)
        $firstDate = min(strtotime($this->profile->getRegistered()), $lastDate - 365 * 24 * 60 * 60);
        // Split into several data points
        $timeOffset = floor(($lastDate - $firstDate) / ($NUM_INTERVALS - 1));

        $usersChartLabels = array("Дата");
        $usersChartValues = array("Предадени решения");
        $index = 0;
        $targetDate = $firstDate;
        for ($i = 0; $i < $NUM_INTERVALS; $i++) {
            $lastValue = $index;
            while ($index < count($submits) && strtotime($submits[$index]->getSubmitted()) <= $targetDate)
                $index++;
            // The last point is the current number of submits
            if ($i + 1 == $NUM_INTERVALS) {
                $index = count($submits);
            }
            array_push($usersChartLabels, gmdate("M. Y", $targetDate));
            array_push($usersChartValues, $index - $lastValue);
            $targetDate += $timeOffset;
        }
        $chart = StatsPage::createChart("LineChart", "activityAreaChart", "",
            $usersChartLabels, $usersChartValues, 780, 200, 90, 70, "none");
        return "
            <h2>Активност</h2>
            {$chart}
            <br>
        ";
    }

    private function getDateStr(string $date): ?string {
        $months = array("Януари", "Февруари", "Март", "Април", "Май", "Юни",
                        "Юли", "Август", "Септември", "Октомври", "Ноември", "Декември");
        $date = explode("-", $date);
        if (count($date) != 3)
            return null;
        $day = intval($date[2]);
        $month = $months[intval($date[1]) - 1];
        $year = intval($date[0]);
        return $day . ". " . $month . ", " . $year;
    }

    private function generalInformation(): string {
        $content = "
                <h2>Информация</h2>
        ";

        $content .= "<b>Име:</b> {$this->profile->getName()}<br>";

        $genderSuffix = $this->profile->getGender() == "female" ? "а" : "";

        // Location
        $location = $this->profile->getTown();
        if ($this->profile->getCountry() != "") {
            if ($location != "") {
                $location .= ", ";
            }
            $location .= $this->profile->getCountry();
        }
        if ($location != "") {
            $content .= "<b>Град:</b> {$location}<br>";
        }

        // Birthdate
        if ($this->profile->getBirthdate() != "0000-00-00") {
            $birthdateDate = $this->getDateStr($this->profile->getBirthdate());
            $content .= "<b>Роден{$genderSuffix} на:</b> {$birthdateDate}<br>";
        }

        // Registered
        if ($this->profile->getRegistered() != "0000-00-00") {
            $registeredDate = $this->getDateStr($this->profile->getRegistered());
            $content .= "<b>Регистриран{$genderSuffix} на:</b> {$registeredDate}<br>";
        }

        // Last seen
        $lastSeen = $this->getDateStr($this->profile->getLastSeen());
        if ($lastSeen != null) {
            $content .= "<b>Последно видян{$genderSuffix} на:</b> {$lastSeen}<br>";
        }

        return $content . "<br>";
    }

    private function additionalInformation(): string {
        $submits = $this->submits;
        if (count($submits) == 0)
            return "";

        $problems = Problem::getAllTasks();
        $languages = array_values($GLOBALS["SUPPORTED_LANGUAGES"]);

        $submitCnt = array();
        $problemDifficulty = array();
        foreach ($problems as $problem) {
            $submitCnt[$problem->getId()] = 0;
            $problemDifficulty[$problem->getId()] = $problem->getDifficulty();
        }

        $langCnt = array();
        foreach ($languages as $language)
            $langCnt[$language] = 0;
        $hourCnt = array();
        for ($hour = 0; $hour < 24; $hour++)
            $hourCnt[$hour] = 0;

        $totalTests = 0;
        $totalTime = 0.0;
        $maxSubmits = 0;

        $difficulties = array(
            "none" => 0,
            "trivial" => 1,
            "easy" => 2,
            "medium" => 3,
            "hard" => 4,
            "brutal" => 5
        );
        $hardest = "none";

        // Gather stats from all user submits
        foreach ($submits as $submit) {
            // Skip submits on games
            if (!array_key_exists($submit->getProblemId(), $problemDifficulty))
                continue;

            // Update number of tests and total CPU time
            $totalTests += count($submit->getExecTime());
            $totalTime += array_sum($submit->getExecTime());

            // Update hardest difficulty of a solved problem
            if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"]) {
                $difficulty = $problemDifficulty[$submit->getProblemId()];
                if ($difficulties[$hardest] < $difficulties[$difficulty])
                    $hardest = $difficulty;
            }

            // Update potential problem with max number of submits
            $submitCnt[$submit->getProblemId()]++;
            $maxSubmits = max($maxSubmits, $submitCnt[$submit->getProblemId()]);

            // Update submits by language
            $langCnt[$submit->getLanguage()]++;

            // Update favourite hour of the day
            // This is rather ugly, I know. It takes the hour from time in YYYY-MM-DD HH:MM:SS format
            $hourCnt[intval(explode(":", explode(" ", $submit->getSubmitted())[1])[0])]++;
        }

        // The user may have submits only on games. Do this check as a fail-safe.
        if ($totalTests == 0)
            return "<br>";

        // Determine favorite language
        $favoriteLang = $languages[0];
        for ($i = 0; $i < count($languages); $i++)
            if ($langCnt[$favoriteLang] < $langCnt[$languages[$i]])
                $favoriteLang = $languages[$i];

        // Determine favorite hour of the day
        $favoriteHour = 0;
        for ($hour = 0; $hour < 24; $hour++) {
            if ($hourCnt[$favoriteHour] < $hourCnt[$hour])
                $favoriteHour = $hour;
        }
        $favoriteTime = sprintf("%02d:00-%02d:59", $favoriteHour, $favoriteHour);

        $content = "
            <h2>Разни</h2>
            <b>Любим програмен език:</b> {$favoriteLang}<br>
            <b>Любим час за решаване:</b> {$favoriteTime}<br>
            <b>Най-много събмити по задача:</b> {$maxSubmits}<br>
            <b>Брой изпълнени тестове:</b> {$totalTests}<br>
            <b>Изразходвано процесорно време:</b> {$totalTime}s<br>
        ";
        if (array_key_exists($hardest, $GLOBALS["PROBLEM_DIFFICULTIES"])) {
            $content .= "    <b>Най-голяма сложност:</b> {$GLOBALS["PROBLEM_DIFFICULTIES"][$hardest]}<br>";
        }
        return $content . '<br>';
    }

    private function getAchievements(): string {
        $fileName = "{$GLOBALS['PATH_DATA']}/achievements/achievements.json";
        $achInfo = json_decode(file_get_contents($fileName), true);
        $achievements = Brain::getAchievements($this->profile->getId());

        $achievementsList = "";
        foreach ($achievements as $achievement) {
            for ($i = 0; $i < count($achInfo); $i++) {
                if ($achInfo[$i]["key"] == $achievement["achievement"]) {
                    # For some reason htmlspecialchars() doesn't work, so do it manually :shrug:
                    $achInfo[$i]['title'] = str_replace("'", "&apos;", $achInfo[$i]['title']);
                    $achInfo[$i]['description'] = str_replace("'", "&apos;", $achInfo[$i]['description']);

                    $achievementsList .= "
                        <div class='achievement' onclick='showAchievement(`{$achInfo[$i]['title']}`, `{$achInfo[$i]['description']}`, 0, 1, true);'>
                            <i class='{$achInfo[$i]['icon']} fa-fw'></i>
                            <span style='font-weight: bold'>{$achInfo[$i]['title']} | {$achievement['date']}</span>
                        </div>
                    ";
                }
            }
        }

        return "
            <h2>Постижения</h2>
            <div>
            {$achievementsList}
            </div>
        ";
    }

    private function profileHead(): string {
        $avatarUrl = "{$GLOBALS['PATH_AVATARS']}/default_avatar.png";
        if ($this->profile->getAvatar() != "") {
            $avatarUrl = "{$GLOBALS['PATH_AVATARS']}/{$this->profile->getAvatar()}";
        }

        return "
            <div class='profile-head'>
                <div class='profile-avatar' style=\"background-image: url('{$avatarUrl}');\"></div>
                <div class='profile-line'></div>
                <div class='profile-username'>{$this->profile->getUsername()}</div>
            </div>
        ";
    }

    private function primaryStats(): string {
        $submits = $this->submits;
        if (count($submits) == 0)
            return "";

        // Calculate percentile
        $ranking = RankingPage::getRanking();
        $position = 0;
        while ($position < count($ranking) && $ranking[$position]->getId() != $this->profile->getId())
            $position++;

        $percentileStat = "100%";
        if ($position <= count($ranking) / 100) {
            $percentileStat = "1%";
        } else if ($position <= count($ranking) / 10) {
            $percentileStat = "10%";
        } else if ($position <= count($ranking) / 4) {
            $percentileStat = "25%";
        } else if ($position <= count($ranking) / 2) {
            $percentileStat = "50%";
        }

        // Calculate accuracy
        $accuracyStat = "0%";
        $accuracy = 0;
        $problemSubmits = array();
        $acceptedProblems = array();
        foreach ($submits as $submit) {
            if (!array_key_exists($submit->getProblemId(), $acceptedProblems)) {
                if (!array_key_exists($submit->getProblemId(), $problemSubmits))
                    $problemSubmits[$submit->getProblemId()] = 0;
                $problemSubmits[$submit->getProblemId()]++;
                if ($submit->getStatus() == $GLOBALS["STATUS_ACCEPTED"]) {
                    $acceptedProblems[$submit->getProblemId()] = true;
                    $accuracy += 1.0 / $problemSubmits[$submit->getProblemId()];
                }
            }
        }
        if (count($acceptedProblems) > 0) {
            $accuracy /= count($acceptedProblems);
            $accuracyStat = sprintf("%.0f%%", 100.0 * $accuracy);
        }

        // Calculate number of solved problems
        $problemsStat = count($acceptedProblems);

        // Calculate number of submits
        $submitsStat = count($submits);

        // Calculate number of actions
        $actionsStat = $this->profile->getActions();

        // Fill in the titles and info
        $percentileTitle = "персентил";
        $percentileInfo = "В най-добрите {$percentileStat} от потребителите.";

        $accuracyTitle = "точност";
        $accuracyInfo = "Предадените събмити до решаване на задача са верни в {$accuracyStat} от случаите.";

        $problemsTitle = $problemsStat == 1 ? "задача" : "задачи";
        $problemsInfo = $problemsStat == 1 ? "Решена 1 задача." : "Решени {$problemsStat} задачи.";

        $submitsTitle = $submitsStat == 1 ? "събмит" : "събмита";
        $submitsInfo = $submitsStat == 1 ? "Предадено 1 решение." : "Предадени {$submitsStat} решения.";

        $actionsTitle = $actionsStat == 1 ? "действие" : "действия";
        $actionsInfo = $actionsStat == 1 ? "Направено 1 действие на сайта." : "Направени {$actionsStat} действия на сайта.";

        return "
            <h2>Статистики</h2>
            <div class='profile-primary-stats'>
                " . getPrimaryStatsCircle($percentileStat, $percentileTitle, $percentileInfo) . "
                " . getPrimaryStatsCircle($accuracyStat, $accuracyTitle, $accuracyInfo) . "
                " . getPrimaryStatsCircle($problemsStat, $problemsTitle, $problemsInfo) . "
                " . getPrimaryStatsCircle($submitsStat, $submitsTitle, $submitsInfo) . "
                " . getPrimaryStatsCircle($actionsStat, $actionsTitle, $actionsInfo) . "
            </div>
            <br>
        ";
    }

    public function getContent(): string {
        $content = "";

        // Profile heading (avatar + nickname)
        // ====================================================================
        $content .= $this->profileHead();

        // Skills radar chart
        // ====================================================================
        $content .= $this->skillRadarChart();

        // User information
        // ====================================================================
        $content .= $this->generalInformation();
        $content .= $this->additionalInformation();

        // Training progress
        // ====================================================================
        // TODO

        // Activity per day
        // ====================================================================
        // TODO

        // Submissions dot information
        // ====================================================================
        $content .= $this->submissionDotChart();

        // Primary stats
        // ====================================================================
        $content .= $this->primaryStats();

       // Submissions over time information
        // ====================================================================
        $content .= $this->submissionActivity();

        // Achievements
        // ====================================================================
        $content .= $this->getAchievements();

        // Record the profile view in the logs
        write_log($GLOBALS["LOG_PROFILE_VIEWS"],
            "User {$this->user->getUsername()} viewed {$this->profile->getUsername()}\'s profile.");
        $this->profile->updateProfileViews($this->user);

        $content .= "
            <div class='box-footer'>
                <div class='tooltip--top' data-tooltip='разглеждания' style='display: inline-block;'>
                    <i class='far fa-eye'></i>
                </div>
                <span style='font-size: 0.75rem;'>{$this->profile->getProfileViews()}</span>
            </div>
        ";
        return inBox($content);
    }
}

?>