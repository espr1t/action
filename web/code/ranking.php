<?php
require_once("db/brain.php");
require_once("common.php");
require_once("page.php");

class RankingPage extends Page {
    public function getTitle(): string {
        return "O(N)::Ranking";
    }

    private static function userCmp(User $user1, User $user2): int {
        if ($user1->numSolved != $user2->numSolved)
            return $user2->numSolved - $user1->numSolved;
        if ($user1->numAchievements != $user2->numAchievements)
            return $user2->numAchievements - $user1->numAchievements;
        return strcmp($user2->getTown(), $user1->getTown());
    }

    /** @return User[] */
    public static function getRanking(): array {
        $users = User::getAllUsers();

        // Remove system and admin accounts from ranking
        /** @var User[] $users */
        $users = array_splice($users, 2);

        $numSolved = array();
        $numAchievements = array();
        foreach ($users as $user) {
            $numSolved[$user->getId()] = 0;
            $numAchievements[$user->getId()] = 0;
        }

        // Intentionally not converting to Submit instances as this is more effective in SQL
        foreach (Brain::getSolvedPerUser() as $solvedCount) {
            if (array_key_exists($solvedCount["userId"], $numSolved))
                $numSolved[$solvedCount["userId"]] = $solvedCount["count"];
        }

        $achievements = Brain::getAchievements();
        foreach ($achievements as $achievement) {
            if (array_key_exists($achievement["userId"], $numAchievements))
                $numAchievements[$achievement["userId"]]++;
        }

        for ($i = 0; $i < count($users); $i++) {
            // Somewhat inconsistent way to append additional info, but oh well...
            $users[$i]->numSolved = $numSolved[$users[$i]->getId()];
            $users[$i]->numAchievements = $numAchievements[$users[$i]->getId()];
        }

        usort($users, array("RankingPage", "userCmp"));
        return $users;
    }

    private function getRankingTable(): string {
        $ranking = "";
        $users = $this->getRanking();

        $place = 0;
        foreach ($users as $user) {
            $place++;
            $profileLink = getUserLink($user->getUsername());
            $displayedTown = $user->getTown() == "" ? "-" : $user->getTown();

            $ranking .= "
                <tr>
                    <td>{$place}</td>
                    <td>{$profileLink}</td>
                    <td>{$user->getName()}</td>
                    <td>{$displayedTown}</td>
                    <td>{$user->numSolved}</td>
                    <td>{$user->numAchievements}</td>
                </tr>
            ";
        }
        return $ranking;
    }

    public function getContent(): string {
        return inBox("
            <h1>Класиране</h1>
            <br>
            <table class='default' id='rankingTable'>
                <tr>
                    <th>#</th>
                    <th>Потребител</th>
                    <th>Име</th>
                    <th onclick='orderRanking(`town`);' style='cursor: pointer;'>Град <i class='fa fa-sort' style='font-size: 0.625rem;'></i></th>
                    <th onclick='orderRanking(`tasks`);' style='cursor: pointer; width: 100px;'>Задачи <i class='fa fa-sort' style='font-size: 0.625rem;'></i></th>
                    <th onclick='orderRanking(`achievements`);' style='cursor: pointer; width: 120px;'>Постижения <i class='fa fa-sort' style='font-size: 0.625rem;'></i></th>
                </tr>
                <tbody>
                    " . $this->getRankingTable() . "
                </tbody>
            </table>
        ");
    }
}

?>
