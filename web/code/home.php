<?php
require_once('common.php');
require_once('page.php');

class HomePage extends Page {
    public function getTitle() {
        return 'O(N)::Home';
    }
    
    public function getContent() {
        $userInfo = userInfo($this->user);
        $text = '<h1>Home</h1>
                 This is a new coding place, part of <a href="http://www.informatika.bg" target="_blank">informatika.bg</a>.
                 
                 <h2>Problems</h2>
                 It will contain a full archive of the problems I\'ve given in various contests (national and international
                 high-school competitions, university classes and competitions, TopCoder, Codeforces, and other).
                 
                 <h2>Training</h2>
                 The problems on the arena will also be organized in a training ladder pointing to different topics in informatics and
                 problems that are related to it. It will be similar to the USACO training, and I hope may prove extremely useful for
                 beginners, as the topics and problems will be in increasing difficulty.
                 
                 <h2>Contests</h2>
                 Additionally, I will organize contests every once in a while, with prizes sponsored by me and/or other people. If you want to
                 sponsor a contest (by money or items for prizes), please contact me through the <a href="">sponsor a contest</a> link at the
                 bottom of the page.
                 
                 <h2>Free for use</h2>
                 Finally, this system is open to all teachers (and students) who want to use it for organizing contests and trainings.
                 Please contact me to give you administrator rights for creating a contest.';
        $content = inBox($text);
        return $userInfo . $content;
    }
}

?>