<?php

// Deterministic local stand-in for the staging mailer endpoint (router for `php -S`).
$body = (string)file_get_contents('php://input');

if (str_contains($body, 'MODE:empty')) {
    return;
}
if (str_contains($body, 'MODE:raw')) {
    header('Content-Type: text/plain');
    echo 'not json, echoing the request: ' . $body;
    return;
}

header('Content-Type: application/json');
echo json_encode(['value' => 200, 'message' => 'accepted for CANARY-RESPONSE-FIELD', 'echo' => $body]);
