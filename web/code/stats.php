<?php
require_once('db/brain.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');

class StatsPage extends Page {
    public function getTitle() {
        return 'O(N)::Stats';
    }

    public function getExtraScripts() {
        return array(
            'https://www.gstatic.com/charts/loader.js'
        );
    }

    public function init() {
        $this->brain = new Brain();
    }

    private function createChart($type, $id, $title, $labels, $values, $width, $height, $percWidth, $percHeight, $legend) {
        $colors = ["#0099FF", "#DD4337", "#129D5A", "#FBCB43", "#8E44AD", "#E67E22", "#16A085", "#2C3E50",
                   "#78A8FC", "#E87467", "#34B67A", "#FCCC44", "#9B59B6", "#F39C12", "#1ABC9C", "#3D566E"];

        $data = array();
        // Actual data
        for ($i = 0; $i < count($labels); $i += 1) {
            array_push($data, array($labels[$i], $values[$i]));
        }

        return '
            <div id="' . $id . '" style="display: inline-block;"></div>
            <script>
                google.charts.load("current");
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
                                "format": "#"
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

    private function problemStats() {
        $content = '
            <h2>Задачи</h2>
        ';

        $content .= '
            <b>Брой задачи:</b> ' . $this->brain->getCount('Problems') . '
            <br><br>
        ';

        $problems = $this->brain->getAllProblems();

        // Pie chart by difficulty
        $difficultyChartLabels = array('Сложност');
        $difficultyChartValues = array('Брой');
        foreach ($GLOBALS['PROBLEM_DIFFICULTIES'] as $difficulty => $name) {
            $cnt = 0;
            foreach ($problems as $problem) {
                if ($problem['difficulty'] == $difficulty) {
                    $cnt += 1;
                }
            }
            array_push($difficultyChartLabels, $name);
            array_push($difficultyChartValues, $cnt);
        }
        $content .= $this->createChart('PieChart', 'difficultiesPieChart', 'Задачи по сложност',
                $difficultyChartLabels, $difficultyChartValues, 340, 300, 90, 85, 'right');

        // Pie chart by tags
        $tagsChartLabels = array('Вид');
        $tagsChartValues = array('Брой');
        foreach ($GLOBALS['PROBLEM_TAGS'] as $tag => $name) {
            $cnt = 0;
            foreach ($problems as $problem) {
                $tags = explode(',', $problem['tags']);
                if (in_array($tag, $tags)) {
                    $cnt += 1;
                }
            }
            array_push($tagsChartLabels, $name);
            array_push($tagsChartValues, $cnt);
        }
        $content .= $this->createChart('PieChart', 'tagsPieChart', 'Задачи по вид',
                $tagsChartLabels, $tagsChartValues, 340, 300, 90, 85, 'right');

        return inBox($content);
    }

    private function submissionStats() {
        $languages = array();
        foreach ($GLOBALS['SUPPORTED_LANGUAGES'] as $lang => $name) {
            $languages[$name] = 0;
        }
        $statuses = array();
        foreach ($GLOBALS['STATUS_DISPLAY_NAME'] as $status => $name) {
            $statuses[$status] = 0;
        }
        $hourHistogram = array_fill(0, 24, 0);

        $submits = $this->brain->getAllSubmits();

        foreach ($submits as $submit) {
            $languages[$submit['language']] += 1;
            $statuses[$submit['status']] += 1;
            $hourHistogram[intval(substr($submit['submitted'], 11, 2))] += 1;
        }

        $content = '
            <h2>Решения</h2>
            <b>Брой предадени решения:</b> ' . count($submits) . '
            <br><br>
        ';

        // Most used programming languages
        $langsChartLabels = array('Език');
        $langsChartValues = array('Решения');
        foreach ($GLOBALS['SUPPORTED_LANGUAGES'] as $lang => $name) {
            array_push($langsChartLabels, $name);
            array_push($langsChartValues, $languages[$name]);
        }
        $content .= $this->createChart('ColumnChart', 'langsColumnChart', 'Решения по програмен език',
                $langsChartLabels, $langsChartValues, 340, 300, 80, 80, 'none');

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
                $statusesChartLabels, $statusesChartValues, 340, 300, 80, 80, 'none');

        $content .= '<br>';

        // Per-hour activity histogram
        $activityHistogramLabels = array('Час');
        $activityHistogramValues = array('Брой');
        for ($i = 0; $i < 24; $i += 1) {
            array_push($activityHistogramValues, $hourHistogram[$i]);
            array_push($activityHistogramLabels, sprintf("%d:00", $i));
        }
        $content .= $this->createChart('ColumnChart', 'activityHistogram', 'Предадени решения по час в денонощието',
                $activityHistogramLabels, $activityHistogramValues, 700, 300, 90, 70, 'none');

        return inBox($content);
    }

    private function userStats() {
        $genders = array();

        $users = $this->brain->getUsers();

        $genders = array('male' => 0, 'female' => 0, 'unknown' => 0);
        $towns = array();
        foreach ($users as $user) {
            if ($user['gender'] == '') {
                $genders['unknown'] += 1;
            } else {
                $genders[$user['gender']] += 1;
            }
            if (!array_key_exists($user['town'], $towns)) {
                $towns[$user['town']] = 0;
            }
            $towns[$user['town']] += 1;
        }
        arsort($towns);

        $content = '
            <h2>Потребители</h2>
            <b>Брой потребители:</b> ' . count($users) . '<br>

            <br><br>
        ';

        // Pie chart by gender
        $genderChartLabels = array('Пол', 'Male', 'Female', 'Unknown');
        $genderChartValues = array('Процент', $genders['male'], $genders['female'],  $genders['unknown']);
        $content .= $this->createChart('PieChart', 'genderPieChart', 'Дял на потребителите по пол',
                $genderChartLabels, $genderChartValues, 340, 300, 90, 85, 'right');

        // Pie chart by town
        $townChartLabels = array('Град');
        $townChartValues = array('Потребители');
        foreach ($towns as $key => $value) {
            // Show top 10 only
            if (count($townChartLabels) > 10)
                break;
            array_push($townChartLabels, $key);
            array_push($townChartValues, $value);
        }
        $content .= $this->createChart('PieChart', 'townPieChart', 'Дял на потребителите по град',
                $townChartLabels, $townChartValues, 340, 300, 90, 85, 'right');

        // Line chart for number of users in time
        $firstDate = strtotime($users[0]['registered']);
        // $lastDate = strtotime($users[count($users) - 1]['registered']);
        $lastDate = time();
        $timeOffset = floor(($lastDate - $firstDate) / 9);

        $usersChartLabels = array('Дата');
        $usersChartValues = array('Брой');
        $index = 0;
        for ($i = 0; $i < 10; $i += 1) {
            $targetDate = gmdate('Y-m-d', $firstDate);
            while ($index < count($users) && $users[$index]['registered'] <= $targetDate) {
                $index += 1;
            }
            // The last point is the current number of users
            if ($i + 1 == 10) {
                $index = count($users);
            }
            array_push($usersChartLabels, $targetDate);
            array_push($usersChartValues, $index);
            $firstDate += $timeOffset;
        }
        $content .= $this->createChart('LineChart', 'usersAreaChart', 'Брой потребители във времето',
                $usersChartLabels, $usersChartValues, 700, 300, 90, 70, 'none');

        $content .= '
            <br>
            TODO:
            <ul>
                <li>Хистограма по възраст</li>
                <li>Графика на брой активни потребители по месец в годината</li>
                <li>Графика на брой активни потребители по час в денонощието</li>
                <li>Word Cloud с постиженията на юзърите</li>
            </ul>
        ';

        return inBox($content);
    }

    public function getContent() {
        $content = inBox('
            <h1>Статистики</h1>
            Произволни статистики за системата и потребителите.
        ');

        // Problem statistics
        $content .= $this->problemStats();

        // Submission statistics
        $content .= $this->submissionStats();

        // User statistics
        $content .= $this->userStats();

        return $content;
    }
    
}

?>