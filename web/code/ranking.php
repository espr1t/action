<?php
require_once('logic/brain.php');
require_once('common.php');
require_once('page.php');

class RankingPage extends Page {
    public function getTitle() {
        return 'O(N)::Ranking';
    }
    
    private function getRanking() {
        $ranking = '';

        $brain = new Brain();
        $usersInfo = $brain->getUsers();

        $place = 0;
        foreach ($usersInfo as $info) {
            $place = $place + 1;

            $info['solved'] = count($brain->getSolved($info['id']));
            $info['achievements'] = count($brain->getAchievements($info['id']));
            $info['score'] = 42;
            $info['link'] = getUserLink($info['username']);
            if ($info['town'] == '') {
                $info['town'] = '-';
            }

            $ranking .= '
                <tr>
                    <td>' . $place . '</td>
                    <td>' . $info['link'] . '</td>
                    <td>' . $info['name'] . '</td>
                    <td>' . $info['town'] . '</td>
                    <td>' . $info['solved'] . '</td>
                    <td>' . $info['achievements'] . '</td>
                    <td>' . $info['score'] . '</td>
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