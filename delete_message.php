<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request.');
}

if (!csrfValidate($_POST['csrf'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid message ID.');
}

$email = $_SESSION['email'] ?? '';

if ($email === '') {
    http_response_code(401);
    exit('Authentication required.');
}

$stmt = dbSelect(
    "SELECT id, auth, recip
     FROM messages
     WHERE id = :id
     LIMIT 1",
    [
        ':id' => $id
    ]
);

$message = $stmt->fetch();

if (!$message) {
    http_response_code(404);
    exit('Message not found.');
}

if (
    $message['auth'] !== $email &&
    $message['recip'] !== $email
) {
    http_response_code(403);
    exit('Unauthorized deletion attempt.');
}

dbExecute(
    "DELETE FROM messages
     WHERE id = :id",
    [
        ':id' => $id
    ]
);

header('Location: messages.php?deleted=1');
exit;