<?php
require_once('logic/brain.php');
require_once('common.php');
require_once('page.php');

class ProfilePage extends Page {
    private $profile;
    
    public function getTitle() {
        return 'O(N)::' . $this->profile->username;
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
    }

    public function getContent() {
        $months = array("Януари", "Февруари", "Март", "Април", "Май", "Юни", "Юли", "Август", "Септември", "Октомври", "Ноември", "Декември");

        // Profile heading (avatar + nickname)
        $avatarUrl = sprintf('%s/%s', $GLOBALS['PATH_AVATARS'], 'default_avatar.png');
        if ($this->profile->avatar != '') {
            $avatarUrl = sprintf('%s/%s', $GLOBALS['PATH_AVATARS'], $this->profile->avatar);
        }

        $head = '
            <div class="profile-head">
                <div class="profile-avatar" style="background-image: url(\'' . $avatarUrl . '\'); "></div>
                <div class="profile-line"></div>
                <div class="profile-username">' . $this->profile->username . '</div>
            </div>
        ';

        $content = '
            <div>
        ';

        // General information
        // ====================================================================
        $content .= '
                <h2>Информация</h2>
        ';
        $content .= '<b>Име:</b> ' . $this->profile->name . '<br>';

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

        // Gender
        $gender = $this->profile->gender;
        $gender = ($gender == 'male' ? 'мъж' : ($gender == 'female' ? 'жена' : ''));
        if ($gender != '') {
            $content .= '<b>Пол:</b> ' . $gender . '<br>';
        }

        // Birthdate
        if ($this->profile->birthdate != '0000-00-00') {
            $birthdate = explode('-', $this->profile->birthdate);
            $birthdateString = $this->profile->gender== 'female' ? 'Родена на:' : 'Роден на:';
            $day = intval($birthdate[2]);
            $month = $months[intval($birthdate[1]) - 1];
            $year = intval($birthdate[0]);
            $content .= '<b>' . $birthdateString . '</b> ' . $day . '. ' . $month . ', ' . $year . '<br>';
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

        $content .= '
            <br>
        ';

        // Training progress
        // ====================================================================
        // TODO:

        /*
        $tried = array();
        $solved = array();
        $submits = $this->profile->submits;
        foreach ($submits as $submit) {
            $submitInfo = Submit::getSubmitInfo($submit);
            if (!in_array($submitInfo['problemId'], $tried)) {
                array_push($tried, $submitInfo['problemId']);
            }
            if ($submitInfo['status'] == $GLOBALS['STATUS_ACCEPTED']) {
                if (!in_array($submitInfo['problemId'], $solved)) {
                    array_push($solved, $submitInfo['problemId']);
                }
            }
        }
        $content .= '
                <h2>Прогрес</h2>
                <b>Брой решени задачи:</b> ' . count($solved) . '<br>
                <b>Брой пробвани задачи:</b> ' . count($tried) . '<br>
                <b>Брой изпратени решения:</b> ' . count($submits) . '<br>
        ';

        $content .= '
            <br>
        ';
        */

        // Charts
        // ====================================================================
        // TODO

        /*
        $content .= '
                <h2>Графики</h2>
        ';

        $content .= '
            <br>
        ';
        */

        // Achievements
        // ====================================================================
        // TODO

        /*
        $fileName = sprintf('%s/achievements.json', $GLOBALS['PATH_ACHIEVEMENTS']);
        $achInfo = json_decode(file_get_contents($fileName), true);

        $achievements = '';
        foreach ($this->profile->achievements as $achievement) {
            for ($i = 0; $i < count($achInfo); $i = $i + 1) {
                if ($achInfo[$i]['id'] == $achievement) {
                    $achievements .= '
                        <div class="achievement" title="' . $achInfo[$i]['description'] . '">
                            <i class="fa fa-' . $achInfo[$i]['icon'] . ' fa-fw"></i>
                            <span style="font-weight: bold">' . $achInfo[$i]['title'] . '</span>
                        </div>
                    ';
                }
            }
        }
        $content .= '
                <h2>Постижения</h2>
                <div>' . $achievements . '</div>
        ';

        $content . '
            </div>
        ';
        */

        return inBox($head . $content);
    }
}

?>