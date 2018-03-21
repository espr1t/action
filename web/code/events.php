<?php

$sse_headers_sent = false;

// SSE Events (https://www.html5rocks.com/en/tutorials/eventsource/basics/)
function sendServerEventData($label, $message) {
    if (!$GLOBALS['sse_headers_sent']) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        // Possible proposed fixes for the buffering problem:
        // header('Transfer-Encoding: chunked');
        // header('X-Accel-Buffering: no');
        $GLOBALS['sse_headers_sent'] = true;
    }

    $data = json_encode(array($label => $message));
    $junk = str_repeat(' ', 32768); // Add 32K to bypass buffering
    echo 'data: ' . $data . $junk . PHP_EOL . PHP_EOL;
    ob_flush();
    flush();
}

function terminateServerEventStream() {
    sendServerEventData('eos', 'EOS');
}

?>
