<?php
require_once('db/brain.php');
require_once('common.php');
require_once('page.php');
require_once('stats.php');
require_once('ranking.php');

class ProfilePage extends Page {
    private $profile;

    public function getTitle() {
        return 'O(N)::' . $this->profile->username;
    }

    public function getExtraStyles() {
        return array('/styles/tooltips.css');
    }

    public function getExtraScripts() {
        return array(
            'https://www.gstatic.com/charts/loader.js',
            '/scripts/d3.min.js',
            '/scripts/radarChart.js'
        );
    }

    public function init() {
        if (!isset($_GET['user'])) {
            header('Location: /error');
            exit();
        }
        $this->profile = User::get($_GET['user']);
        if ($this->profile == null) {
            header('Location: /error');
            exit();
        }
        $this->brain = new Brain();
    }

    private function getPercentage($total, $solved, $tag) {
        if ($total[$tag] == 0)
            return 1.0;
        return $solved[$tag] / $total[$tag];
    }

    # TODO: Why not using this?
    private function getTopUsersSkills($problems) {
        // Get average stats for top 10% of the users
        $allUsers = $this->brain->getAllUsers();
        $allSolved = array();
        for ($i = 0; $i < count($allUsers); $i = $i + 1) {
            $allUsers[$i]['solved'] = $this->brain->getSolved($allUsers[$i]['id']);
            array_push($allSolved, count($allUsers[$i]['solved']));
        }
        sort($allSolved);
        array_reverse($allSolved);
        $targetCount = $allSolved[count($allSolved) / 10];
        $topSolvedCount = array();
        $topUsersCount = 0;
        // Count how many times each of the problems is solved by a top 10% user
        foreach ($allUsers as $user) {
            if (count($user['solved']) >= $targetCount) {
                $topUsersCount += 1;
                foreach ($user['solved'] as $problem) {
                    if (!array_key_exists($problem, $topSolvedCount)) {
                        $topSolvedCount[$problem] = 0;
                    }
                    $topSolvedCount[$problem] += 1;
                }
            }
        }

        $topUsersTags = array();
        foreach ($GLOBALS['PROBLEM_TAGS'] as $tag => $name) {
            $topUsersTags[$tag] = 0;
        }

        // Sum the tags for each of the solved problems by the top users
        foreach ($problems as $problem) {
            $tags = explode(',', $problem['tags']);
            foreach ($tags as $tag) {
                if (array_key_exists($problem['id'], $topSolvedCount)) {
                    $topUsersTags[$tag] += $topSolvedCount[$problem['id']];
                }
            }
        }
        // Take the average for the top users
        $topUsersTags = array_map(function($el) use($topUsersCount) {return $el / $topUsersCount;}, $topUsersTags);
        return $topUsersTags;
    }

    private function skillRadarChart() {
        // Radar chart library taken from: http://www.visualcinnamon.com/2015/10/different-look-d3-radar-chart.html

        $content = '
             <div class="radarChart" style="float: right; background-color: #FFFFFF; margin-right: -40px;"></div>
        ';

        // Evaluate how good the users is at each topic (tag).
        $totalTags = array();
        $solvedTags = array();
        foreach ($GLOBALS['PROBLEM_TAGS'] as $tag => $name) {
            $totalTags[$tag] = 0;
            $solvedTags[$tag] = 0;
        }

        $problems = $this->brain->getAllProblems();
        $solved = $this->brain->getSolved($this->profile->id);

        foreach ($problems as $problem) {
            $tags = explode(',', $problem['tags']);
            foreach ($tags as $tag) if ($tag != '') {
                $totalTags[$tag] += 1;
                if (in_array($problem['id'], $solved)) {
                    $solvedTags[$tag] += 1;
                }
            }
        }

        // Prepare the data for the radar chart
        $skillsData = array();

        // Current user
        $skills = array();
        $remain = 12; // Limit to 12 so the chart doesn't get overcrowded
        foreach ($GLOBALS['PROBLEM_TAGS'] as $tag => $name) {
            if ($remain >= 0) {
                $remain -= 1;
                array_push($skills,
                    array('axis' => $name, 'value' => $this->getPercentage($totalTags, $solvedTags, $tag))
                );
            }
        }
        array_push($skillsData, $skills);

        // Invoke the JS code to generate the radar chart
        $content .= '
            <script>
                var data = ' . json_encode($skillsData) . ';
                var options = {
                    w: 200,
                    h: 200,
                    margin: {top: 40, right: 55, bottom: 40, left: 55},
                    maxValue: 1.0,
                    levels: 5,
                    roundStrokes: false,
                    color: d3.scale.ordinal().range(["#0099FF", "#DD4337", "#00A0B0", "#CC333F", "#EDC951"])
                };
                RadarChart(".radarChart", data, options);
            </script>
        ';
        return $content;
    }

    private function submissionDotChart() {
        $submits = $this->brain->getUserSubmits($this->profile->id);
        if (count($submits) == 0)
            return '';

        $content = '
                <h2>Предадени Решения</h2>
        ';

        // Submits dot chart
        $content .= '<div class="profile-dot-box">';
        foreach ($submits as $submit) {
            $color = 'gray';
            switch ($submit['status']) {
                case $GLOBALS['STATUS_ACCEPTED']:
                    $color = 'green';
                    break;
                case $GLOBALS['STATUS_WRONG_ANSWER']:
                    $color = 'red';
                    break;
                case $GLOBALS['STATUS_TIME_LIMIT']:
                    $color = 'yellow';
                    break;
                case $GLOBALS['STATUS_MEMORY_LIMIT']:
                    $color = 'dull-purple';
                    break;
                case $GLOBALS['STATUS_RUNTIME_ERROR']:
                    $color = 'asphalt';
                    break;
                case $GLOBALS['STATUS_COMPILATION_ERROR']:
                    $color = 'blue';
                    break;
                case $GLOBALS['STATUS_INTERNAL_ERROR']:
                    $color = 'black';
                    break;
                default:
                    $color = 'gray';
            }
            $content .= '<div class="profile-dot background-' . $color . '" title="' . $submit['status'] . '"></div>';
        }
        $content .= '</div>';
        return $content . '<br>';
    }

    private function submissionActivity() {
        $content = '
                <h2>Активност</h2>
        ';
        $submits = $this->brain->getUserSubmits($this->profile->id);

        // Submits activity over time
        $lastDate = time();
        // Make the interval be at least one year (useful for recently registered users)
        $firstDate = min(strtotime($this->profile->registered), $lastDate - 365 * 24 * 60 * 60);
        // Split into 16 data points
        $timeOffset = floor(($lastDate - $firstDate) / 15);

        $usersChartLabels = array('Дата');
        $usersChartValues = array('Предадени решения');
        $index = 0;
        $targetDate = $firstDate;
        for ($i = 0; $i < 16; $i++) {
            $lastValue = $index;
            while ($index < count($submits) && strtotime($submits[$index]['submitted']) <= $targetDate) {
                $index += 1;
            }
            // The last point is the current number of submits
            if ($i + 1 == 16) {
                $index = count($submits);
            }
            array_push($usersChartLabels, gmdate('M. Y', $targetDate));
            array_push($usersChartValues, $index - $lastValue);
            $targetDate += $timeOffset;
        }
        $content .= StatsPage::createChart('LineChart', 'activityAreaChart', '',
                $usersChartLabels, $usersChartValues, 780, 200, 90, 70, 'none');
        return $content . '<br>';
    }

    private function getDateStr($date) {
        $months = array("Януари", "Февруари", "Март", "Април", "Май", "Юни",
                        "Юли", "Август", "Септември", "Октомври", "Ноември", "Декември");
        $date = explode('-', $date);
        if (count($date) != 3)
            return null;
        $day = intval($date[2]);
        $month = $months[intval($date[1]) - 1];
        $year = intval($date[0]);
        return $day . '. ' . $month . ', ' . $year;
    }

    private function generalInformation() {
        $content = '
                <h2>Информация</h2>
        ';

        $content .= '<b>Име:</b> ' . $this->profile->name . '<br>';

        $genderSuffix = $this->profile->gender == 'female' ? 'а' : '';

        // Location
        $location = $this->profile->town;
        if ($this->profile->country != '') {
            if ($location != '') {
                $location .= ', ';
            }
            $location .= $this->profile->country;
        }
        if ($location != '') {
            $content .= '<b>Град:</b> ' . $location . '<br>';
        }

        // Birthdate
        if ($this->profile->birthdate != '0000-00-00') {
            $birthdateText = 'Роден' . $genderSuffix . ' на:';
            $birthdateDate = $this->getDateStr($this->profile->birthdate);
            $content .= '<b>' . $birthdateText . '</b> ' . $birthdateDate . '<br>';
        }

        // Registered
        if ($this->profile->registered != '0000-00-00') {
            $registeredText = 'Регистриран' . $genderSuffix . ' на:';
            $registeredDate = $this->getDateStr($this->profile->registered);
            $content .= '<b>' . $registeredText . '</b> ' . $registeredDate . '<br>';
        }

        // Last seen
        $lastSeen = $this->getDateStr($this->profile->lastSeen);
        if ($lastSeen != null) {
            $content .= '<b>Последно видян' . $genderSuffix . ' на:</b> ' . $lastSeen . '<br>';
        }

        return $content . '<br>';
    }

    private function additionalInformation() {
        $content = '
                <h2>Разни</h2>
        ';

        $submits = $this->brain->getUserSubmits($this->profile->id);
        if (count($submits) == 0)
            return '';

        $problems = $this->brain->getAllProblems();
        $problemDifficulty = array();
        foreach ($problems as $problem) {
            $problemDifficulty[$problem['id']] = $problem['difficulty'];
            $problemType[$problem['id']] = $problem['type'];
        }

        $langCnt = array();
        $hourCnt = array();
        $submitCnt = array();
        $totalTests = 0;
        $totalTime = 0.0;
        $maxSubmits = 0;

        $difficulties = array(
            'none' => 0,
            'trivial' => 1,
            'easy' => 2,
            'medium' => 3,
            'hard' => 4,
            'brutal' => 5
        );
        $hardest = 'none';

        // Gather stats from all user submits
        foreach ($submits as $submit) {
            // Skip submits on games
            if (!array_key_exists($submit['problemId'], $problemType))
                continue;

            $execTime = explode(',', $submit['execTime']);
            $totalTests += count($execTime);
            $totalTime += array_sum($execTime);

            if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                if (array_key_exists($submit['problemId'], $problemDifficulty)) {
                    $difficulty = $problemDifficulty[$submit['problemId']];
                    if ($difficulties[$hardest] < $difficulties[$difficulty])
                        $hardest = $difficulty;
                }
            }

            if (!array_key_exists($submit['problemId'], $submitCnt))
                $submitCnt[$submit['problemId']] = 0;
            $submitCnt[$submit['problemId']]++;
            $maxSubmits = max(array($maxSubmits, $submitCnt[$submit['problemId']]));

            if (!array_key_exists($submit['language'], $langCnt))
                $langCnt[$submit['language']] = 0;
            $langCnt[$submit['language']]++;

            $hour = intval(explode(':', explode(' ', $submit['submitted'])[1])[0]);
            if (!array_key_exists($hour, $hourCnt))
                $hourCnt[$hour] = 0;
            $hourCnt[$hour]++;
        }
        if ($totalTests == 0)
            return '<br>';

        // Determine favorite language
        $languages = array('C++', 'Java', 'Python');
        $favoriteLang = 0;
        while (!array_key_exists($languages[$favoriteLang], $langCnt))
            $favoriteLang++;
        for ($i = $favoriteLang + 1; $i < count($languages); $i++)
            if (array_key_exists($languages[$i], $langCnt))
                if ($langCnt[$languages[$i]] > $langCnt[$languages[$favoriteLang]])
                    $favoriteLang = $i;
        $favoriteLang = $languages[$favoriteLang];

        // Determine favorite hour of the day
        $favoriteTime = 0;
        while (!array_key_exists($favoriteTime, $hourCnt))
            $favoriteTime++;
        for ($hour = $favoriteTime + 1; $hour < 24; $hour++) {
            if (array_key_exists($hour, $hourCnt))
                if ($hourCnt[$hour] > $hourCnt[$favoriteTime])
                    $favoriteTime = $hour;
        }
        $favoriteTime = sprintf("%02d:00-%02d:59", $favoriteTime, $favoriteTime);

        if (!array_key_exists($hardest, $GLOBALS['PROBLEM_DIFFICULTIES'])) {
            $hardestProblem = '';
        } else {
            $hardestProblem = $GLOBALS['PROBLEM_DIFFICULTIES'][$hardest];
        }

        $mostSubmits = $maxSubmits;
        $numExecutedTests = $totalTests;
        $totalProcessorTime = $totalTime;

        $content .= '<b>Любим програмен език:</b> ' . $favoriteLang . '<br>';
        $content .= '<b>Любим час за решаване:</b> ' . $favoriteTime . '<br>';
        if ($hardestProblem != '') {
            $content .= '<b>Най-голяма сложност:</b> ' . $hardestProblem . '<br>';
        }
        $content .= '<b>Най-много събмити по задача:</b> ' . $mostSubmits . '<br>';
        $content .= '<b>Брой изпълнени тестове:</b> ' . $numExecutedTests . '<br>';
        $content .= '<b>Изразходвано процесорно време:</b> ' . $totalProcessorTime . 's<br>';

        return $content . '<br>';
    }

    private function trainingProgress() {
        $content = '
                <h2>Прогрес</h2>
        ';

        $submits = $this->brain->getUserSubmits($this->profile->id);
        $tried = array();
        $solved = array();

        foreach ($submits as $submit) {
            if (!in_array($submit['problemId'], $tried)) {
                array_push($tried, $submit['problemId']);
            }
            if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                if (!in_array($submit['problemId'], $solved)) {
                    array_push($solved, $submit['problemId']);
                }
            }
        }

        $content .= '
                <b>Решени задачи:</b> ' . count($solved) . '<br>
                <b>Пробвани задачи:</b> ' . count($tried) . '<br>
                <b>Изпратени решения:</b> ' . count($submits) . '<br>
        ';
        return $content . '<br>';
    }

    private function getAchievements() {
        $fileName = sprintf('%s/achievements.json', $GLOBALS['PATH_ACHIEVEMENTS']);
        $achInfo = json_decode(file_get_contents($fileName), true);
        $achievements = $this->brain->getAchievements($this->profile->id);

        $content = '<h2>Постижения</h2>';
        $content .= '<div>';
        foreach ($achievements as $achievement) {
            for ($i = 0; $i < count($achInfo); $i = $i + 1) {
                if ($achInfo[$i]['key'] == $achievement['achievement']) {
                    $content .= '
                        <div class="achievement" onclick="showAchievement(\'' . addslashes($achInfo[$i]['title']) . '\', \'' .  addslashes($achInfo[$i]['description']) . '\', 0, 1, true);">
                            <i class="' . $achInfo[$i]['icon'] . ' fa-fw"></i>
                            <span style="font-weight: bold">' . $achInfo[$i]['title'] . ' | ' . $achievement['date'] . '</span>
                        </div>
                    ';
                }
            }
        }
        $content .= '</div>';
        return $content;
    }

    private function profileHead() {
        $avatarUrl = sprintf('%s/%s', $GLOBALS['PATH_AVATARS'], 'default_avatar.png');
        if ($this->profile->avatar != '') {
            $avatarUrl = sprintf('%s/%s', $GLOBALS['PATH_AVATARS'], $this->profile->avatar);
        }

        $content = '
            <div class="profile-head">
                <div class="profile-avatar" style="background-image: url(\'' . $avatarUrl . '\'); "></div>
                <div class="profile-line"></div>
                <div class="profile-username">' . $this->profile->username . '</div>
            </div>
        ';
        return $content;
    }

    private function primaryStats() {
        $submits = $this->brain->getUserSubmits($this->profile->id);
        if (count($submits) == 0)
            return '';

        $content = '
                <h2>Статистики</h2>
        ';

        // Calculate percentile
        $ranking = RankingPage::getRanking();
        $position = 0;
        while ($position < count($ranking) && $ranking[$position]['id'] != $this->profile->id)
            $position++;
        if ($position >= count($ranking)) {
            $position = 0;
        }

        $percentileStat = '100%';
        if ($position <= count($ranking) / 100) {
            $percentileStat = '1%';
        } else if ($position <= count($ranking) / 10) {
            $percentileStat = '10%';
        } else if ($position <= count($ranking) / 4) {
            $percentileStat = '25%';
        } else if ($position <= count($ranking) / 2) {
            $percentileStat = '50%';
        }

        // Calculate accuracy
        $accuracyStat = "0%";
        $accuracy = 0;
        $problemSubmits = array();
        $acceptedProblems = array();
        foreach ($submits as $submit) {
            if (!array_key_exists($submit['problemId'], $acceptedProblems)) {
                if (!array_key_exists($submit['problemId'], $problemSubmits))
                    $problemSubmits[$submit['problemId']] = 0;
                $problemSubmits[$submit['problemId']]++;
                if ($submit['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                    $acceptedProblems[$submit['problemId']] = true;
                    $accuracy += 1.0 / $problemSubmits[$submit['problemId']];
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
        $actionsStat = $this->profile->actions;

        // Fill in the titles and info
        $percentileTitle = 'персентил';
        $percentileInfo = 'В най-добрите ' . $percentileStat . ' от потребителите.';

        $accuracyTitle = 'точност';
        $accuracyInfo = 'Предадените събмити до решаване на задача са верни в ' . $accuracyStat . ' от случаите.';

        $problemsTitle = $problemsStat == 1 ? 'задача' : 'задачи';
        $problemsInfo = $problemsStat == 1 ? 'Решена 1 задача.' : 'Решени ' . $problemsStat . ' задачи.';

        $submitsTitle = $submitsStat == 1 ? 'събмит' : 'събмита';
        $submitsInfo = $submitsStat == 1 ? 'Предадено 1 решение.' : 'Предадени ' . $submitsStat . ' решения.';

        $actionsTitle = $actionsStat == 1 ? 'действие' : 'действия';
        $actionsInfo = $actionsStat == 1 ? 'Направено 1 действие на сайта.' : 'Направени ' . $actionsStat . ' действия на сайта.';

        $percentileStats = getPrimaryStatsCircle($percentileStat, $percentileTitle, $percentileInfo);
        $accuracyStats = getPrimaryStatsCircle($accuracyStat, $accuracyTitle, $accuracyInfo);
        $problemsStats = getPrimaryStatsCircle($problemsStat, $problemsTitle, $problemsInfo);
        $submitsStats = getPrimaryStatsCircle($submitsStat, $submitsTitle, $submitsInfo);
        $actionsStats = getPrimaryStatsCircle($actionsStat, $actionsTitle, $actionsInfo);

        $content .= '
            <div class="profile-primary-stats">
                ' . $percentileStats . '
                ' . $accuracyStats . '
                ' . $problemsStats . '
                ' . $submitsStats . '
                ' . $actionsStats . '
            </div>
        ';
        return $content . '<br>';
    }

    private function updateProfileViews() {
        if ($this->user->id < 1)
            return;

        $lastViewers = array();
        if ($this->profile->lastViewers != '')
            $lastViewers = explode(',', $this->profile->lastViewers);
        for ($i = 0; $i < count($lastViewers); $i++) {
            if ($lastViewers[$i] == $this->user->username) {
                return;
            }
        }
        array_unshift($lastViewers, $this->user->username);
        if (count($lastViewers) > 3) {
            $lastViewers = array_slice($lastViewers, 0, 3);
        }
        $this->profile->lastViewers = implode(',', $lastViewers);
        $this->profile->profileViews++;
        $this->brain->updateUserInfo($this->profile);
    }

    public function getContent() {
        $content = '';

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

        /*
        // Training progress
        // ====================================================================
        // TODO
        $content .= $this->trainingProgress();
        */

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
        $logMessage = sprintf('User %s viewed %s\'s profile.', $this->user->username, $this->profile->username);
        write_log($GLOBALS['LOG_PROFILE_VIEWS'], $logMessage);
        $this->updateProfileViews();

        $content .= '
            <div class="box-footer">
                <div class="tooltip--top" data-tooltip="разглеждания" style="display: inline-block;">
                    <i class="far fa-eye"></i>
                </div>
                <span style="font-size: 0.75rem;">' . $this->profile->profileViews . '</span>
            </div>
        ';
        return inBox($content);
    }
}

?>