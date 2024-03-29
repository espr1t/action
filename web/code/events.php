<?php

$sse_headers_sent = false;

// SSE Events (https://www.html5rocks.com/en/tutorials/eventsource/basics/)
function sendServerEventData(string $label, string $message): void {
    global $sse_headers_sent;

    // If this is the first response, send the headers
    if (!$sse_headers_sent) {
        header("Content-Type: text/event-stream");
        header("Cache-Control: no-cache");
        // Possible proposed fixes for the buffering problem:
//         header("Transfer-Encoding: chunked");
//         header("X-Accel-Buffering: no");
        $sse_headers_sent = true;

        // Also release the session lock so the user can make other requests
        session_write_close();
    }

    $data = json_encode(array($label => $message));
    $junk = str_repeat(" ", 32768); // Add 32K to bypass buffering
    echo "data: {$data} {$junk}\n\n";
    ob_flush();
    flush();
}

function checkServerEventClient(): bool {
    // Please note we need to send something in order to see that the client has disconnected
    sendServerEventData("heartbeat", "PING");
    return !connection_aborted();
}

function restartEventStream(): void {
    sendServerEventData("res", "RES");
}

function terminateServerEventStream(): void {
    sendServerEventData("eos", "EOS");
}

?>
