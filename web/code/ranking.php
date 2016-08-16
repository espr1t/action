<?php
require_once('common.php');
require_once('page.php');

class RankingPage extends Page {
    public function getTitle() {
        return 'O(N)::Ranking';
    }
    
    private function getRanking() {
        $ranking = '';

        $place = 0;
        foreach (scandir($GLOBALS['PATH_USERS']) as $entry) {
            if (!preg_match(User::$user_info_re, basename($entry))) {
                continue;
            }

            $fileName = sprintf("%s/%s", $GLOBALS['PATH_USERS'], $entry);
            $info = json_decode(file_get_contents($fileName), true);

            if ($info['username'] == 'anonymous') {
                continue;
            }

            $place = $place + 1;
            $user = getUserLink($info['username']);
            $solved = 1;
            $achievements = 1;
            $score = 42;
            if ($info['town'] == '') {
                $info['town'] = '-';
            }
            $ranking .= '
                <tr>
                    <td>' . $place . '</td>
                    <td>' . $user . '</td>
                    <td>' . $info['name'] . '</td>
                    <td>' . $info['town'] . '</td>
                    <td>' . $solved . '</td>
                    <td>' . $achievements . '</td>
                    <td>' . $score . '</td>
                </tr>
            ';
        }
        return $ranking;
    }

    public function getContent() {
        $ranking = $this->getRanking();
        $table = '
            <table class="default">
                <tr>
                    <th>#</th>
                    <th>Потребител</th>
                    <th>Име</th>
                    <th>Град</th>
                    <th>Задачи</th>
                    <th>Постижения</th>
                    <th>Точки</th>
                </tr>
                ' . $ranking . '
            </table>
        ';
        $content = '
            <h1>Класиране</h1>
            <br>
        ';
        return inBox($content . $table);
    }
    
}

?>