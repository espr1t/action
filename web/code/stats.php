<?php
require_once('db/brain.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');

class StatsPage extends Page {
    public function getTitle() {
        return 'O(N)::Stats';
    }

    public function getExtraStyles() {
        return array('/styles/tooltips.css');
    }

    public function getExtraScripts() {
        return array(
            'https://www.gstatic.com/charts/loader.js',
            '/scripts/d3.min.js',
            '/scripts/d3.layout.cloud.js',
            '/scripts/d3.layout.cloud.wrapper.js'
        );
    }

    public function init() {
        $this->brain = new Brain();
        $this->FIRST_DATE = $this->brain->getUser(1)['registered'];
    }

    private function createWordCloud($wordCounts) {
        arsort($wordCounts);
        $words = array();
        foreach ($wordCounts as $word => $count) {
            array_push($words, array('text' => $word, 'size' => $count));
        }

        return '
            <div id="wordcloud"></div>
            <script>
                var words = ' . json_encode($words) . ';
                d3.wordcloud()
                    .size([780, 350])
                    .font("Century Gothic")
                    .selector("#wordcloud")
                    .words(words)
                    .scale("sqrt")
                    .start();

            </script>
        ';
    }

    public static function createChart($type, $id, $title, $labels, $values, $width, $height, $percWidth, $percHeight, $legend) {
        $colors = ["#0099FF", "#DD4337", "#129D5A", "#FBCB43", "#8E44AD", "#E67E22", "#16A085", "#2C3E50",
                   "#78A8FC", "#E87467", "#34B67A", "#FCCC44", "#9B59B6", "#F39C12", "#1ABC9C", "#3D566E"];

        $data = array();
        // Actual data
        for ($i = 0; $i < count($labels); $i++) {
            array_push($data, array($labels[$i], $values[$i]));
        }

        return '
            <div id="' . $id . '" style="display: inline-block;"></div>
            <script>
                google.charts.load("current", {"packages":["corechart"]});
                google.charts.setOnLoadCallback(drawVisualization);

                function drawVisualization() {
                    var wrapper = new google.visualization.ChartWrapper({
                        chartType: "' . $type . '",
                        dataTable: ' . json_encode($data) . ',
                        options: {
                            "title": "' . $title . '",
                            "titleTextStyle": {
                                "fontSize": 16
                            },
                            "labels": "name",
                            "fontName": "\'Century Gothic\', \'Trebuchet MS\', \'Ubuntu\', sans-serif",
                            "pieSliceText": "percentage",
                            "width": ' . $width . ',
                            "height": ' . $height . ',
                            "chartArea": {
                                "width": "' . $percWidth . '%",
                                "height": "' . $percHeight . '%"
                            },
                            "legend": {
                                "position": "'. $legend .'"
                            },
                            "colors": ' . json_encode($colors) . ',
                            "pieHole": 0.5,
                            "vAxis": {
                                "format": "#",
                                "viewWindowMode": "explicit",
                                "viewWindow": {
                                    min: 0
                                }
                            },
                            "hAxis": {
                                "textStyle": {
                                    "fontSize": 10
                                }
                            },
                            "curveType": "function",
                        },
                        containerId: "' . $id . '"
                    });
                    wrapper.draw();
                }
            </script>
        ';
    }

    private function mainStats() {
        $content = '
            <h1>Статистики</h1>
            Произволни статистики за системата и потребителите.
        ';

        $problems = $this->brain->getAllProblems();
        $games = $this->brain->getAllGames();
        $users = $this->brain->getAllUsers();
        $usersInfo = $this->brain->getAllUsersInfo();
        $submits = $this->brain->getAllSubmits();

        $problemStat = count($problems);
        $problemTitle = 'задачи';
        $problemInfo = 'Брой задачи на системата.';

        $gameStat = count($games);
        $gameTitle = 'игри';
        $gameInfo = 'Брой игри на системата.';

        $userStat = count($users);
        $userTitle = 'потребители';
        $userInfo = 'Брой потребители на системата.';

        $submitStat = count($submits);
        $submitTitle = 'решения';
        $submitInfo = 'Брой предадени решения.';

        $actionStat = 0;
        foreach ($usersInfo as $info) {
            $actionStat += $info['actions'];
        }
        $actionStat = sprintf("%dK", $actionStat / 1000);
        $actionTitle = 'действия';
        $actionInfo = 'Брой действия, направени от потребителите.';

        $problemStats = getPrimaryStatsCircle($problemStat, $problemTitle, $problemInfo);
        $gameStats = getPrimaryStatsCircle($gameStat, $gameTitle, $gameInfo);
        $userStats = getPrimaryStatsCircle($userStat, $userTitle, $userInfo);
        $submitStats = getPrimaryStatsCircle($submitStat, $submitTitle, $submitInfo);
        $actionStats = getPrimaryStatsCircle($actionStat, $actionTitle, $actionInfo);

        $content .= '
            <div class="profile-primary-stats">
                ' . $problemStats . '
                ' . $gameStats . '
                ' . $userStats . '
                ' . $submitStats . '
                ' . $actionStats . '
            </div>
        ';
        return inBox($content);
    }

    private function problemStats() {
        $content = '
            <h2>Задачи</h2>
        ';

        $problems = $this->brain->getAllProblems();

        // Pie chart by difficulty
        $difficultyChartLabels = array('Сложност');
        $difficultyChartValues = array('Брой');
        foreach ($GLOBALS['PROBLEM_DIFFICULTIES'] as $difficulty => $name) {
            $cnt = 0;
            foreach ($problems as $problem) {
                if ($problem['difficulty'] == $difficulty) {
                    $cnt++;
                }
            }
            array_push($difficultyChartLabels, $name);
            array_push($difficultyChartValues, $cnt);
        }
        $content .= $this->createChart('PieChart', 'difficultiesPieChart', 'Задачи по сложност',
                $difficultyChartLabels, $difficultyChartValues, 380, 300, 90, 85, 'right');

        // Pie chart by tags
        $tagsChartLabels = array('Вид');
        $tagsChartValues = array('Брой');
        foreach ($GLOBALS['PROBLEM_TAGS'] as $tag => $name) {
            $cnt = 0;
            foreach ($problems as $problem) {
                $tags = explode(',', $problem['tags']);
                if (in_array($tag, $tags)) {
                    $cnt++;
                }
            }
            array_push($tagsChartLabels, $name);
            array_push($tagsChartValues, $cnt);
        }
        $content .= $this->createChart('PieChart', 'tagsPieChart', 'Задачи по вид',
                $tagsChartLabels, $tagsChartValues, 380, 300, 90, 85, 'right');

        return inBox($content);
    }

    private function submissionStats() {
        $content = '
            <h2>Решения</h2>
        ';

        $languages = array();
        foreach ($GLOBALS['SUPPORTED_LANGUAGES'] as $lang => $name) {
            $languages[$name] = 0;
        }
        $statuses = array();
        foreach ($GLOBALS['STATUS_DISPLAY_NAME'] as $status => $name) {
            $statuses[$status] = 0;
        }
        $hourHistogram = array_fill(0, 24, 0);
        $monthHistogram = array_fill(0, 12, 0);

        $submits = $this->brain->getAllSubmits();
        $numSubmits = count($submits);

        foreach ($submits as $submit) {
            $languages[$submit['language']]++;
            $statuses[$submit['status']]++;
            $hourHistogram[intval(substr($submit['submitted'], 11, 2))]++;
            $monthHistogram[intval(substr($submit['submitted'], 5, 2)) - 1]++;
        }

        // Most used programming languages
        $langsChartLabels = array('Език');
        $langsChartValues = array('Решения');
        foreach ($GLOBALS['SUPPORTED_LANGUAGES'] as $lang => $name) {
            array_push($langsChartLabels, $name);
            array_push($langsChartValues, $languages[$name]);
        }
        $content .= $this->createChart('ColumnChart', 'langsColumnChart', 'Решения по програмен език',
                $langsChartLabels, $langsChartValues, 380, 300, 80, 80, 'none');

        // Most common results statuses
        $statusesChartLabels = array('Статус');
        $statusesChartValues = array('Брой');
        foreach ($GLOBALS['STATUS_DISPLAY_NAME'] as $status => $name) {
            if ($statuses[$status] > 0) {
                array_push($statusesChartLabels, $status);
                array_push($statusesChartValues, $statuses[$status]);
            }
        }
        $content .= $this->createChart('ColumnChart', 'statusesColumnChart', 'Резултати от решенията',
                $statusesChartLabels, $statusesChartValues, 380, 300, 80, 80, 'none');

        $content .= '<br>';

        // Per-hour activity histogram
        $hourlyActivityHistogramLabels = array('Час');
        $hourlyActivityHistogramValues = array('Брой');
        for ($i = 0; $i < 24; $i++) {
            array_push($hourlyActivityHistogramValues, $hourHistogram[$i]);
            array_push($hourlyActivityHistogramLabels, sprintf("%d:00", $i));
        }
        $content .= $this->createChart('ColumnChart', 'hourlyActivityHistogram', 'Предадени решения по час в денонощието',
                $hourlyActivityHistogramLabels, $hourlyActivityHistogramValues, 780, 300, 90, 70, 'none');

        // Per-month activity histogram
        $months = array('Януари', 'Февруари', 'Март', 'Април', 'Май', 'Юни', 'Юли', 'Август', 'Септември', 'Октомври', 'Ноември', 'Декември');
        $monthlyActivityHistogramLabels = array('Месец');
        $monthlyActivityHistogramValues = array('Брой');
        for ($i = 0; $i < 12; $i++) {
            array_push($monthlyActivityHistogramValues, $monthHistogram[$i]);
            array_push($monthlyActivityHistogramLabels, $months[$i]);
        }
        $content .= $this->createChart('ColumnChart', 'monthlyActivityHistogram', 'Предадени решения по месец в годината',
                $monthlyActivityHistogramLabels, $monthlyActivityHistogramValues, 780, 300, 90, 70, 'none');

        // Activity over time line chart
        $NUM_TIME_POINTS = 15;
        $firstDate = strtotime($this->FIRST_DATE);
        $lastDate = time();
        $timeOffset = floor(($lastDate - $firstDate) / ($NUM_TIME_POINTS - 1));

        $totalActivityChartLabels = array('Дата');
        $totalActivityChartValues = array('Брой');
        $index = 0;
        for ($i = 0; $i < $NUM_TIME_POINTS; $i++) {
            $lastIndex = $index;
            $targetDate = gmdate('Y-m-d', $firstDate);
            $shownDate = gmdate('M Y', $firstDate);
            while ($index < $numSubmits && $submits[$index]['submitted'] <= $targetDate) {
                $index++;
            }
            // The last point is the current number of users
            if ($i == $NUM_TIME_POINTS - 1) {
                $index = $numSubmits;
            }
            array_push($totalActivityChartLabels, $shownDate);
            array_push($totalActivityChartValues, $index - $lastIndex);
            $firstDate += $timeOffset;
        }
        $content .= $this->createChart('AreaChart', 'totalActivityAreaChart', 'Предадени решения през времето',
                $totalActivityChartLabels, $totalActivityChartValues, 780, 300, 90, 70, 'none');

        return inBox($content);
    }

    private $TOWN_ALIASES = array(
        'Gorna Orqhovica' => 'Горна Оряховица',
        'Belene' => 'Белене',
        'Sofia' => 'София',
        'Sofiq' => 'София',
        'Plovdiv' => 'Пловдив',
        'Ruse' => 'Русе',
        'Rousse' => 'Русе',
        'Varna' => 'Варна',
        'Gabrovo' => 'Габрово',
        'Yambol' => 'Ямбол',
        'Belovo' => 'Белово',
        'Kazanlak' => 'Казанлък',
        'Burgas' => 'Бургас',
        'Sliven' => 'Сливен',
        'Vratsa' => 'Враца',
        'Shumen' => 'Шумен',
        'Veliko Tarnovo' => 'Велико Търново',
        'Smolyan' => 'Смолян',
        'Dobrich' => 'Добрич',
        'Blagoevgrad' => 'Благоевград',
        'Razgrad' => 'Разград',
        'Montana' => 'Монтана',
        'Pleven' => 'Плевен'
    );

    private function userStats() {
        $content = '
            <h2>Потребители</h2>
        ';

        $usersArr = $this->brain->getAllUsers();
        $usersInfoArr = $this->brain->getAllUsersInfo();
        $numUsers = count($usersArr);

        $users = array();
        for ($i = 0; $i < $numUsers; $i++) {
            array_push($users, User::instanceFromArray($usersArr[$i], $usersInfoArr[$i]));
        }

        // Pie chart by gender
        $genders = array('male' => 0, 'female' => 0, 'unknown' => 0);
        foreach ($users as $user) {
            if ($user->gender == '') {
                $genders['unknown']++;
            } else {
                $genders[$user->gender]++;
            }
        }

        $genderChartLabels = array('Пол', 'Мъж', 'Жена', 'Незададен');
        $genderChartValues = array('Процент', $genders['male'], $genders['female'],  $genders['unknown']);
        $content .= $this->createChart('PieChart', 'genderPieChart', 'Дял на потребителите по пол',
                $genderChartLabels, $genderChartValues, 380, 300, 90, 85, 'right');

        // Pie chart by town
        $towns = array();
        foreach ($users as $user) {
            // Make the town in proper First Letter Uppercase style
            $town = mb_convert_case(mb_strtolower($user->town), MB_CASE_TITLE, 'utf-8');
            // Convert it to Cyrillic if a town in Bulgaria
            if (array_key_exists($town, $this->TOWN_ALIASES))
                $town = $this->TOWN_ALIASES[$town];
            // Uncomment this if you want to see all Latin-lettered towns
            // if (ctype_alpha(substr($town, 0, 1)))
            //     echo $town . ', ';
            if (!array_key_exists($town, $towns)) {
                $towns[$town] = 0;
            }
            $towns[$town]++;
        }
        arsort($towns);

        $townChartLabels = array('Град');
        $townChartValues = array('Потребители');
        foreach ($towns as $key => $value) {
            // Do not show empty strings (for people who haven't entered it)
            if ($key == '')
                continue;
            // Show top 10 only
            if (count($townChartLabels) > 10)
                break;
            array_push($townChartLabels, $key);
            array_push($townChartValues, $value);
        }
        $content .= $this->createChart('PieChart', 'townPieChart', 'Дял на потребителите по град',
                $townChartLabels, $townChartValues, 380, 300, 90, 85, 'right');

        // Line chart for number of users in time
        $NUM_TIME_POINTS = 15;
        $firstDate = strtotime($this->FIRST_DATE);
        $lastDate = time();
        $timeOffset = floor(($lastDate - $firstDate) / ($NUM_TIME_POINTS - 1));

        $usersChartLabels = array('Дата');
        $usersChartValues = array('Брой');
        $index = 0;
        for ($i = 0; $i < $NUM_TIME_POINTS; $i++) {
            $shownDate = gmdate('M Y', $firstDate);
            $targetDate = gmdate('Y-m-d', $firstDate);
            while ($index < $numUsers && $users[$index]->registered <= $targetDate) {
                $index++;
            }
            // The last point is the current number of users
            if ($i == $NUM_TIME_POINTS - 1) {
                $index = $numUsers;
            }
            array_push($usersChartLabels, $shownDate);
            array_push($usersChartValues, $index);
            $firstDate += $timeOffset;
        }
        $content .= $this->createChart('AreaChart', 'usersAreaChart', 'Брой регистрирани потребители',
                $usersChartLabels, $usersChartValues, 780, 300, 90, 70, 'none');

        // Histogram of user's age
        // Instead of simply taking the current time and subtracting the birthday, we'll do
        // something more elaborate - we'll consider the "current time" the time of their last
        // action, thus preventing "aging" of accounts if they are inactive. Thus, a person who
        // last visited the site 4 years ago when he/she was 16 years old will be considered as
        // 16 year old (instead of 20, which he/she currently is).
        $ageHistogram = array_fill(0, 81, 0);
        foreach ($users as $user) {
            if ($user->birthdate == '0000-00-00')
                continue;
            $lastAction = explode(' ', $user->lastSeen)[0];
            // Safe guard in case lastSeen is not populated for some reason
            // (it should be after http://espr1t.net/bugs/view.php?id=510 is done)
            if ($lastAction == '0000-00-00')
                $lastAction = $user->registered;
            // User added birthdate in the future
            if ($user->birthdate >= $lastAction)
                continue;
            $age = floor((strtotime($lastAction) - strtotime($user->birthdate)) / (365 * 24 * 60 * 60));
            if ($age <= 80) {
                $ageHistogram[$age]++;
            }
        }

        $ageHistogramLabels = array('Възраст');
        $ageHistogramValues = array('Брой');
        for ($i = 0; $i <= 80; $i++) {
            array_push($ageHistogramValues, $ageHistogram[$i]);
            array_push($ageHistogramLabels, sprintf("%d", $i));
        }
        $content .= $this->createChart('ColumnChart', 'ageHistogram', 'Брой потребители по възраст',
                $ageHistogramLabels, $ageHistogramValues, 780, 300, 90, 70, 'none');

        return inBox($content);
    }

    private function wordCloud() {
        $brain = new Brain();
        $achievements = $brain->getAchievements();

        $keyCount = array();
        foreach ($achievements as $achievement) {
            $key = $achievement['achievement'];
            if (!array_key_exists($key, $keyCount))
                $keyCount[$key] = 0;
            $keyCount[$key] = $keyCount[$key] + 1;
        }

        $achievementsFile = file_get_contents($GLOBALS['PATH_ACHIEVEMENTS'] . '/achievements.json');
        $achievementsData = json_decode($achievementsFile, true);

        $achievementCount = array();
        foreach ($achievementsData as $achievement) {
            if (array_key_exists($achievement['key'], $keyCount)) {
                $achievementCount[$achievement['title']] = $keyCount[$achievement['key']];
            }
        }
        $content = '
            <h2>Постижения</h2>
            <br>
        ' . $this->createWordCloud($achievementCount);

        return inBox($content);
    }

    public function getContent() {
        $content = '';

        // Main stats
        $content .= $this->mainStats();

        // Problem statistics
        $content .= $this->problemStats();

        // Submission statistics
        $content .= $this->submissionStats();

        // User statistics
        $content .= $this->userStats();

        // Achievements Word-Cloud
        $content .= $this->wordCloud();

        return $content;
    }

}

?>