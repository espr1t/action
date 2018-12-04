<?php
require_once('db/brain.php');
require_once('common.php');
require_once('page.php');

class ProfilePage extends Page {
    private $profile;

    public function getTitle() {
        return 'O(N)::' . $this->profile->username;
    }

    public function getExtraScripts() {
        return array(
            '/scripts/d3.min.js', '/scripts/radarChart.js'
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

    private function getTopUsersSkills($problems) {
        // Get average stats for top 10% of the users
        $allUsers = $this->brain->getUsers();
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
            foreach ($tags as $tag) {
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

        /*
        // Top 10% of all users
        $topUsersTags = $this->getTopUsersSkills($problems);
        $skills = array();
        $remain = 12; // Limit to 12 so the chart doesn't get overcrowded
        foreach ($GLOBALS['PROBLEM_TAGS'] as $tag => $name) {
            if ($remain >= 0) {
                $remain -= 1;
                array_push($skills,
                    array('axis' => $name, 'value' => $this->getPercentage($totalTags, $topUsersTags, $tag))
                );
            }
        }
        array_push($skillsData, $skills);
        */

        // Invoke the JS code to generate the radar chart
        $content .= '
            <script>
                var data = ' . json_encode($skillsData) . ';
                var options = {
                    w: 200,
                    h: 200,
                    margin: {top: 40, right: 50, bottom: 50, left: 50},
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
        $content = '
                <h2>Предадени Решения</h2>
        ';
        $submits = $this->brain->getUserSubmits($this->profile->id);
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
        return $content;
    }

    private function generalInformation() {
        $content = '
                <h2>Информация</h2>
        ';
        $months = array("Януари", "Февруари", "Март", "Април", "Май", "Юни", "Юли", "Август", "Септември", "Октомври", "Ноември", "Декември");

        $content .= '<b>Име:</b> ' . $this->profile->name . '<br>';

        // Birthdate
        if ($this->profile->birthdate != '0000-00-00') {
            $birthdate = explode('-', $this->profile->birthdate);
            $birthdateString = $this->profile->gender== 'female' ? 'Родена на:' : 'Роден на:';
            $day = intval($birthdate[2]);
            $month = $months[intval($birthdate[1]) - 1];
            $year = intval($birthdate[0]);
            $content .= '<b>' . $birthdateString . '</b> ' . $day . '. ' . $month . ', ' . $year . '<br>';
        }

        // Gender
        $gender = $this->profile->gender;
        $gender = ($gender == 'male' ? 'мъж' : ($gender == 'female' ? 'жена' : ''));
        if ($gender != '') {
            $content .= '<b>Пол:</b> ' . $gender . '<br>';
        }

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

        // Registered
        $registered = explode('-', $this->profile->registered);
        if (count($registered) == 3) {
            $registeredString = $this->profile->gender == 'female' ? 'Регистрирана на:' : 'Регистриран на:';
            $day = intval($registered[2]);
            $month = $months[intval($registered[1]) - 1];
            $year = intval($registered[0]);
            $content .= '<b>' . $registeredString . '</b> ' . $day . '. ' . $month . ', ' . $year . '<br>';
        }

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

    public function getContent() {
        $content = '';

        // Profile heading (avatar + nickname)
        // ====================================================================
        $content .= $this->profileHead();

        // Percentile
        // ====================================================================
        // TODO

        // Skills radar chart
        // ====================================================================
        $content .= $this->skillRadarChart();

        // General information
        // ====================================================================
        $content .= $this->generalInformation();

        // Training progress
        // ====================================================================
        // TODO
        $content .= $this->trainingProgress();

        // Activity per day
        // ====================================================================
        // TODO

        // Submission information
        // ====================================================================
        $content .= $this->submissionDotChart();

        // Achievements
        // ====================================================================
        $content .= $this->getAchievements();

        return inBox($content);
    }
}

?>