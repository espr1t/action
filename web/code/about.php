<?php
require_once('common.php');
require_once('page.php');

class AboutPage extends Page {
    public function getTitle() {
        return 'O(N)::About';
    }
    
    public function getContent() {
        $text = '<h1>About</h1>
                 <div class="separator"></div>

                 This is a new coding place, part of <a href="http://www.informatika.bg" target="_blank">informatika.bg</a>.
                 It is created and maintained by <a href="http://espr1t.net/me.html" target="_blank">Alexander Georgiev</a>.
                 
                 <h2>Problems</h2>
                 It will contain a full archive of the problems I\'ve given in various contests (national and international
                 high-school competitions, university classes and competitions, TopCoder, Codeforces, and other).
                 
                 <h2>Training</h2>
                 The problems on the arena will also be organized in a training ladder pointing to different topics in informatics and
                 problems that are related to it. It will be similar to the <a href="http://train.usaco.org/usacogate" target="_blank">USACO training</a>,
                 and I hope may prove extremely useful for beginners, as the topics and problems will be in increasing difficulty.
                 
                 <h2>Contests</h2>
                 Additionally, I will organize contests every once in a while, with prizes sponsored by me and/or other people. If you want to
                 sponsor a contest (with money or prizes), please contact me through the <a href="">sponsor a contest</a> link at the
                 bottom of the page.

                 <h2>The System</h2>
                 The system is created and supported by me, Alexander Georgiev.
                 It is free for use and is available as a <a href="https://github.com" target="_blank">GitHub</a> project:
                 <a href="https://github.com/espr1t/action" target="_blank">Act!O(n)</a>.

                 <h2>Contact</h2>
                 If you spot issues with the system or problems, please use the link "report a problem" below.
                 For other matters you can contact me at <a href="mailto:thinkcreative@outlook.com" target="_self">thinkcreative@outlook.com</a>.
                 Finally, you can like us and discuss things with other visitors of the page on our
                 <a href="https://www.facebook.com/informatika.bg/" target="_blank">Facebook page</a>.
        ';
        $content = inBox($text);
        return $content;
    }
    
}

?>