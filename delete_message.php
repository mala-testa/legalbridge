<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

/*
|--------------------------------------------------------------------------
| Authentication and session integrity
|--------------------------------------------------------------------------
|
| Your requireLogin() function checks both login status and the session
| fingerprint.
|
*/

requireLogin();

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| POST requests only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Invalid request method.');
}

/*
|--------------------------------------------------------------------------
| CSRF validation
|--------------------------------------------------------------------------
*/

if (!csrfValidate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Invalid request.');
}

/*
|--------------------------------------------------------------------------
| Validate message ID
|--------------------------------------------------------------------------
*/

$message_id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $message_id === false ||
    $message_id === null ||
    $message_id <= 0
) {
    http_response_code(400);
    exit('Invalid message ID.');
}

$message_id = (int)$message_id;

/*
|--------------------------------------------------------------------------
| Verify message existence and ownership
|--------------------------------------------------------------------------
|
| The signed-in user must be either the sender or recipient.
|
*/

$messageStmt = dbSelect(
    "SELECT id
     FROM messages
     WHERE id = :message_id
       AND (
            sender_id = :sender_user_id
            OR recipient_id = :recipient_user_id
       )
     LIMIT 1",
    [
        ':message_id'        => $message_id,
        ':sender_user_id'    => $user_id,
        ':recipient_user_id' => $user_id
    ]
);

$message = $messageStmt->fetch();

if (!$message) {
    /*
     * Use one response for both a missing message and a message owned by
     * somebody else. This avoids revealing whether another user's message
     * ID exists.
     */
    http_response_code(404);
    exit('Message not found or access denied.');
}

/*
|--------------------------------------------------------------------------
| Delete message with ownership protection
|--------------------------------------------------------------------------
|
| The ownership condition is repeated here so authorization is enforced
| at the moment the DELETE operation executes.
|
*/

try {
    $deleted = dbExecute(
        "DELETE FROM messages
         WHERE id = :message_id
           AND (
                sender_id = :sender_user_id
                OR recipient_id = :recipient_user_id
           )",
        [
            ':message_id'        => $message_id,
            ':sender_user_id'    => $user_id,
            ':recipient_user_id' => $user_id
        ]
    );

    if (!$deleted) {
        throw new RuntimeException(
            'Message deletion query failed.'
        );
    }

    header('Location: messages.php?deleted=1');
    exit;
} catch (Throwable $exception) {
    lb_log_error(
        'Message deletion failed for user ID '
            . $user_id
            . ', message ID '
            . $message_id
            . ': '
            . $exception->getMessage()
    );

    http_response_code(500);

    exit('The message could not be deleted. Please try again.');
}
