<?php

$sse_headers_sent = false;

// SSE Events (https://www.html5rocks.com/en/tutorials/eventsource/basics/)
function sendServerEventData($label, $message) {
    if (!$GLOBALS['sse_headers_sent']) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        $GLOBALS['sse_headers_sent'] = true;
    }

    $data = json_encode(array($label => $message));
    echo 'data: ' . $data . PHP_EOL . PHP_EOL;
    ob_flush();
    flush();
}

function terminateServerEventStream() {
    sendServerEventData('eos', 'EOS');
}

?>
