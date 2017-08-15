<?php
require_once('db/brain.php');
require_once('common.php');
require_once('page.php');

class RankingPage extends Page {
    public function getTitle() {
        return 'O(N)::Ranking';
    }

    private static function userCmp($user1, $user2) {
        if ($user1['solved'] != $user2['solved'])
            return $user2['solved'] - $user1['solved'];
        if ($user1['achievements'] != $user2['achievements'])
            return $user2['achievements'] - $user1['achievements'];
        return strcmp($user2['town'], $user1['town']);
    }

    public static function getRanking() {
        $brain = new Brain();
        $usersInfo = $brain->getUsers();

        // Remove system and admin accounts from ranking
        $usersInfo = array_splice($usersInfo, 2);

        for ($i = 0; $i < count($usersInfo); $i += 1) {
            $usersInfo[$i]['solved'] = count($brain->getSolved($usersInfo[$i]['id']));
            $usersInfo[$i]['achievements'] = count($brain->getAchievements($usersInfo[$i]['id']));
            $usersInfo[$i]['link'] = getUserLink($usersInfo[$i]['username']);
        }
        usort($usersInfo, array('RankingPage', 'userCmp'));
        return $usersInfo;
    }

    private function getRankingTable() {
        $ranking = '';
        $usersInfo = $this->getRanking();

        $place = 0;
        foreach ($usersInfo as $info) {
            $place = $place + 1;

            if ($info['town'] == '')
                $info['town'] = '-';

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
        $rankingTableContent = $this->getRankingTable();
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
                ' . $rankingTableContent . '
                </tbody>
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