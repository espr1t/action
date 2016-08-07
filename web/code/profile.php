<?php
require_once('common.php');
require_once('page.php');

class ProfilePage extends Page {
    private $profile;
    
    public function getTitle() {
        return 'O(N)::' . $this->profile->getUsername();
    }

    public function init() {
        if (!isset($_GET['user'])) {
            header('Location: /error');
            exit();
        }
        $this->profile = User::getUser($_GET['user']);
        if ($this->profile == null) {
            header('Location: /error');
            exit();
        }
    }

    public function getContent() {
        $months = array("Януари", "Февруари", "Март", "Април", "Май", "Юни", "Юли", "Август", "Септември", "Октомври", "Ноември", "Декември");

        // Profile heading (avatar + nickname)
        $avatarUrl = '/data/users/avatars/default_avatar.png';
        if ($this->profile->getAvatar() != '') {
            $avatarUrl = '/data/users/avatars/' . $this->profile->getAvatar();
        }

        $head = '
            <div class="profile-head">
                <div class="profile-avatar" style="background-image: url(\'' . $avatarUrl . '\'); "></div>
                <div class="profile-line"></div>
                <div class="profile-username">' . $this->profile->getUsername() . '</div>
            </div>
        ';

        $info = '
            <div>
        ';

        // General information
        // ====================================================================
        $info .= '
                <h2>Информация</h2>
                <div class="separator"></div>
        ';
        $info .= '<b>Име:</b> ' . $this->profile->getName() . '<br>';

        // Location
        $location = $this->profile->getTown();
        if ($this->profile->getCountry() != '') {
            if ($location != '') {
                $location .= ', ';
            }
            $location .= $this->profile->getCountry();
        }
        if ($location != '') {
            $info .= '<b>Град:</b> ' . $location . '<br>';
        }

        // Gender
        $gender = $this->profile->getGender();
        $gender = ($gender == 'male' ? 'мъж' : ($gender == 'female' ? 'жена' : ''));
        if ($gender != '') {
            $info .= '<b>Пол:</b> ' . $gender . '<br>';
        }

        // Birthdate
        $birthdate = explode('-', $this->profile->getBirthdate());
        if (count($birthdate) == 3) {
            $birthdateString = $this->profile->getGender() == 'female' ? 'Родена на:' : 'Роден на:';
            $day = intval($birthdate[2]);
            $month = $months[intval($birthdate[1]) - 1];
            $year = intval($birthdate[0]);
            $info .= '<b>' . $birthdateString . '</b> ' . $day . '. ' . $month . ', ' . $year . '<br>';
        }

        // Registered
        $registered = explode('-', $this->profile->getRegistered());
        if (count($registered) == 3) {
            $registeredString = $this->profile->getGender() == 'female' ? 'Регистрирана на:' : 'Регистриран на:';
            $day = intval($registered[2]);
            $month = $months[intval($registered[1]) - 1];
            $year = intval($registered[0]);
            $info .= '<b>' . $registeredString . '</b> ' . $day . '. ' . $month . ', ' . $year . '<br>';
        }

        $info .= '
            <br>
        ';

        // Training progress
        // ====================================================================
        $solved = $this->profile->getSolved();
        $tried = $this->profile->getTried();
        $submissions = $this->profile->getSubmissions();
        $info .= '
                <h2>Прогрес</h2>
                <div class="separator"></div>
                <b>Брой решени задачи:</b> ' . count($solved) . '<br>
                <b>Брой пробвани задачи:</b> ' . count($tried) . '<br>
                <b>Брой изпратени решения:</b> ' . count($submissions) . '<br>
        ';

        $info .= '
            <br>
        ';

        // Charts
        // ====================================================================
        $info .= '
                <h2>Графики</h2>
                <div class="separator"></div>
        ';

        $info .= '
            <br>
        ';

        // Achievements
        // ====================================================================
        $achievements = '';
        /*
        for ($achievement : $this->profile->getAchievements()) {
        }
        */
        $info .= '
                <h2>Постижения</h2>
                <div class="separator"></div>
                <div>' . $achievements . '</div>
        ';

        $info . '
            </div>
        ';

        return inBox($head . $info);
    }
}

?>