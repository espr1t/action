<?php
require_once('db/brain.php');
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
                </tr>
            ';
        }

        return $ranking;
    }

    public function getContent() {
        $ranking = $this->getRanking();
        $table = '
            <table class="default" id="rankingTable">
                <tr>
                    <th>#</th>
                    <th>Потребител</th>
                    <th>Име</th>
                    <th onclick="orderRanking(\'town\');" style="cursor: pointer;">Град <i class="fa fa-sort" style="font-size: 0.625rem;"></i></th>
                    <th onclick="orderRanking(\'tasks\');" style="cursor: pointer; width: 100px;">Задачи <i class="fa fa-sort" style="font-size: 0.625rem;"></i></th>
                    <th onclick="orderRanking(\'achievements\');" style="cursor: pointer; width: 120px;">Постижения <i class="fa fa-sort" style="font-size: 0.625rem;"></i></th>
                </tr>
                <tbody>
                ' . $ranking . '
                </tbody>
            </table>
        ';
        $content = '
            <h1>Класиране</h1>
            <br>
        ';
        $initialOrder = '
            <script>orderRanking(\'tasks\');</script>
        ';
        return inBox($content . $table . $initialOrder);
    }
    
}

?>