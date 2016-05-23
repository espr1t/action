<?php

function newLine() {
    return '
';
}

function inBox($content) {
    return '
            <div class="box">' . newLine() . 
                $content . '
            </div>' . newLine();
}

function userInfo($user) {
    $content = '
                <div class="userInfo">
                    user: <div class="user">' . $user->getHandle() . '</div>
                </div>' . newLine();
    return $content;
}

?>