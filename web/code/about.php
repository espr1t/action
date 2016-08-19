<?php
require_once('common.php');
require_once('page.php');

class AboutPage extends Page {
    public function getTitle() {
        return 'O(N)::About';
    }
    
    public function getContent() {
        $content = inBox('
            <h1>About</h1>
            This is a new coding place, part of <a href="http://www.informatika.bg" target="_blank">informatika.bg</a>.
            It is created and maintained by <a href="http://espr1t.net/me.html" target="_blank">Alexander Georgiev</a>.

            <br><br>

            <h2>Problems</h2>
            It will contain a full archive of the problems I\'ve given in various contests (national and international
            high-school competitions, university classes and competitions, TopCoder, Codeforces, and other).

            <br><br>

            <h2>Training</h2>
            The problems on the arena will also be organized in a training ladder pointing to different topics in informatics and
            problems that are related to it. It will be similar to the <a href="http://train.usaco.org/usacogate" target="_blank">USACO training</a>,
            and I hope may prove extremely useful for beginners, as the topics and problems will be in increasing difficulty.

            <br><br>

            <h2>Contests</h2>
            Sometime in the future I plan to start organizing contests every once in a while, with prizes sponsored by me and/or other people.
            I consider having the possibility allow sponsoring contests (either by gifts or problems or both).

            <br><br>

            <h2>The System</h2>
            The system is being developed by me, Alexander Georgiev.
            It is free for use and is available as a <a href="https://github.com" target="_blank">GitHub</a> project:
            <a href="https://github.com/espr1t/action" target="_blank">Act!O(n)</a>.

            <br><br>

            <h2>Contact</h2>
            If you spot issues with the system or problems, please use the link "report a problem" below.
            For other matters you can contact me at <a href="mailto:thinkcreative@outlook.com" target="_self">thinkcreative@outlook.com</a>.
            Finally, you can like us and discuss things with other visitors of the page on our
            <a href="https://www.facebook.com/informatika.bg/" target="_blank">Facebook page</a>.
        ');
        return $content;
    }
    
}

?>