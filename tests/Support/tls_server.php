<?php

// HTTPS stand-in for the staging mailer endpoint (a self-signed certificate valid for 127.0.0.1).
// Usage: php tls_server.php <port> <pem-file> <request-log-file>
// The response depends on a MODE:<name> marker in the request body. Every request
// that completes a TLS handshake is appended to the request log.

[$script, $port, $pem, $requestLog] = $argv;

$context = stream_context_create(['ssl' => ['local_cert' => $pem, 'verify_peer' => false, 'allow_self_signed' => true]]);
$server = stream_socket_server("tls://127.0.0.1:$port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, "cannot listen: $errstr\n");
    exit(1);
}

while (true) {
    $connection = @stream_socket_accept($server, 3600);
    if ($connection === false) {
        continue; // failed handshake (for example an untrusted certificate) or timeout
    }
    stream_set_timeout($connection, 5);
    $headers = '';
    while (($line = fgets($connection)) !== false && $line !== "\r\n") {
        $headers .= $line;
    }
    if ($headers === '') {
        fclose($connection);
        continue; // no HTTP request was received: the client aborted the handshake
    }
    preg_match('/Content-Length:\s*(\d+)/i', $headers, $length);
    $body = isset($length[1]) ? (string)fread($connection, (int)$length[1]) : '';
    file_put_contents($requestLog, json_encode(['line' => strtok($headers, "\r\n"), 'body' => $body]) . "\n", FILE_APPEND);

    $respond = static function (string $status, string $extra, string $payload) use ($connection): void {
        fwrite($connection, "HTTP/1.1 $status\r\n$extra" . 'Content-Length: ' . strlen($payload) . "\r\nConnection: close\r\n\r\n" . $payload);
    };
    if (str_contains($body, 'MODE:redirect')) {
        $respond('302 Found', "Location: http://127.0.0.1:1/redirected\r\n", '');
    } elseif (str_contains($body, 'MODE:empty')) {
        $respond('200 OK', '', '');
    } elseif (str_contains($body, 'MODE:raw')) {
        $respond('200 OK', "Content-Type: text/plain\r\n", 'not json, echoing the request: ' . $body);
    } elseif (str_contains($body, 'MODE:secrets')) {
        $respond('200 OK', "Content-Type: application/json\r\n", json_encode(['value' => 'CANARY-OTP-482913', 'error' => 'token=CANARY-TOKEN', 'message' => 'CANARY-RESPONSE-FIELD']));
    } else {
        $respond('200 OK', "Content-Type: application/json\r\n", json_encode(['value' => 200, 'message' => 'CANARY-RESPONSE-FIELD']));
    }
    fclose($connection);
}
