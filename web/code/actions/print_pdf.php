<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../entities/grader.php');
require_once(__DIR__ . '/../entities/problem.php');

$problem = null;
if (isset($_GET['problemId'])) {
    $problem = Problem::get($_GET['problemId']);
}
if (isset($_GET['game'])) {
    $problem = getGameByName($_GET['game']);
}
if ($problem != null) {
    $problemName = 'action-' . getGameUrlName($problem->name);

    // Get current page URI and replace pdf with print
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $url = str_replace("pdf", "print", $url);

    // Invoke the Grader to print the PDF
    $grader = new Grader();
    $data = $grader->print_to_pdf($url);

    if ($data != null) {
        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
        header("Cache-Control: public"); // needed for internet explorer
        header("Content-Type: application/pdf");
        header("Content-Transfer-Encoding: Binary");
        header("Content-Length:" . strlen($data));
        header("Content-Disposition: attachment; filename=" . $problemName);
        echo $data;
        die();
    } else {
        error_log('Error while trying to print a pdf (URL was: ' . $url . ')!');
    }
}
?>
