<?php

function newLine() {
    return '
';
}

function getPageContent($pagePath) {
    return file_get_contents($pagePath);
}

?>