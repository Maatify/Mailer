<?php

// Minimal SMTP stand-in with implicit TLS (a self-signed certificate valid for 127.0.0.1).
// Usage: php smtp_server.php <port> <pem-file> <event-log-file>
// A recipient containing "reject" is refused with 550. Every command line and every
// received message is appended to the event log as one JSON line.

[$script, $port, $pem, $eventLog] = $argv;

$context = stream_context_create(['ssl' => ['local_cert' => $pem, 'verify_peer' => false, 'allow_self_signed' => true]]);
$server = stream_socket_server("tls://127.0.0.1:$port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, "cannot listen: $errstr\n");
    exit(1);
}

$event = static function (array $data) use ($eventLog): void {
    file_put_contents($eventLog, json_encode($data) . "\n", FILE_APPEND);
};

while (true) {
    $connection = @stream_socket_accept($server, 3600);
    if ($connection === false) {
        continue; // failed handshake or timeout
    }
    stream_set_timeout($connection, 5);
    $reply = static function (string $line) use ($connection): void {
        fwrite($connection, $line . "\r\n");
    };
    $reply('220 test ESMTP');
    $accepted = 0;
    while (($line = fgets($connection)) !== false) {
        $line = rtrim($line, "\r\n");
        $command = strtoupper(substr($line, 0, 4));
        if ($command === 'EHLO' || $command === 'HELO') {
            $event(['command' => 'EHLO']);
            fwrite($connection, "250-test\r\n250-AUTH LOGIN PLAIN\r\n250 8BITMIME\r\n");
        } elseif ($command === 'AUTH') {
            $event(['command' => 'AUTH']);
            if (stripos($line, 'AUTH LOGIN') === 0) {
                $reply('334 VXNlcm5hbWU6');
                fgets($connection);
                $reply('334 UGFzc3dvcmQ6');
                fgets($connection);
            }
            $reply('235 authenticated');
        } elseif ($command === 'MAIL') {
            $event(['command' => 'MAIL']);
            $reply('250 ok');
        } elseif ($command === 'RCPT') {
            if (stripos($line, 'reject') !== false) {
                $event(['command' => 'RCPT', 'result' => 'refused']);
                $reply('550 mailbox refused');
            } else {
                $accepted++;
                $event(['command' => 'RCPT', 'result' => 'accepted', 'line' => $line]);
                $reply('250 ok');
            }
        } elseif ($command === 'DATA') {
            if ($accepted === 0) {
                $reply('503 no valid recipients');
                continue;
            }
            $reply('354 end with <CRLF>.<CRLF>');
            $message = '';
            while (($row = fgets($connection)) !== false && $row !== ".\r\n") {
                $message .= $row;
            }
            $event(['command' => 'DATA', 'message' => $message]);
            $reply('250 queued');
        } elseif ($command === 'QUIT') {
            $reply('221 bye');
            break;
        } else {
            $reply('250 ok');
        }
    }
    fclose($connection);
}
