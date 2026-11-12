<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!verify_session_fingerprint(true)) {
    lb_destroy_session();

    http_response_code(403);

    exit('Session integrity check failed. Please log in again.');
}

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Validate message ID
|--------------------------------------------------------------------------
*/

$message_id = filter_input(
    INPUT_GET,
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
| Load message and verify ownership
|--------------------------------------------------------------------------
|
| The ownership condition is part of the query. A user cannot retrieve a
| message unless they are its sender or recipient.
|
*/

$messageStmt = dbSelect(
    "SELECT
        m.id,
        m.sender_id,
        m.recipient_id,
        m.message,
        m.created_at,
        sender.name AS sender_name,
        sender.email AS sender_email,
        recipient.name AS recipient_name,
        recipient.email AS recipient_email
     FROM messages m
     INNER JOIN users sender
        ON m.sender_id = sender.id
     INNER JOIN users recipient
        ON m.recipient_id = recipient.id
     WHERE m.id = :message_id
       AND (
            m.sender_id = :sender_user_id
            OR m.recipient_id = :recipient_user_id
       )
     LIMIT 1",
    [
        ':message_id'       => $message_id,
        ':sender_user_id'   => $user_id,
        ':recipient_user_id' => $user_id
    ]
);

$message = $messageStmt->fetch();

if (!$message) {
    http_response_code(404);
    exit('Message not found or access denied.');
}

/*
|--------------------------------------------------------------------------
| Format message information
|--------------------------------------------------------------------------
*/

$created_timestamp = strtotime(
    $message['created_at'] ?? ''
);

$created_date = $created_timestamp !== false
    ? date('F j, Y, g:i A', $created_timestamp)
    : 'Unknown';

$is_sender =
    (int)$message['sender_id'] === $user_id;

/*
|--------------------------------------------------------------------------
| Render page
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    Message
</h2>

<div class="lb-card">

    <p>
        <strong>From:</strong>
        <?= e($message['sender_name'] ?? 'Unknown') ?>

        <?php if (!empty($message['sender_email'])): ?>
            (<?= e($message['sender_email']) ?>)
        <?php endif; ?>
    </p>

    <p>
        <strong>To:</strong>
        <?= e($message['recipient_name'] ?? 'Unknown') ?>

        <?php if (!empty($message['recipient_email'])): ?>
            (<?= e($message['recipient_email']) ?>)
        <?php endif; ?>
    </p>

    <p>
        <strong>Date:</strong>
        <?= e($created_date) ?>
    </p>

    <p>
        <strong>Message:</strong>
    </p>

    <p>
        <?= nl2br(e($message['message'] ?? '')) ?>
    </p>

    <div
        class="actions-cell"
        style="
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        ">

        <a
            class="lb-btn"
            href="forward_message.php?id=<?= $message_id ?>">
            Forward
        </a>

        <form
            method="post"
            action="delete_message.php"
            style="display: inline;">

            <input
                type="hidden"
                name="csrf"
                value="<?= e(csrfToken()) ?>">

            <input
                type="hidden"
                name="id"
                value="<?= $message_id ?>">

            <button
                type="submit"
                class="lb-btn lb-danger"
                onclick="return confirm('Delete this message?')">
                Delete
            </button>

        </form>

        <a
            class="lb-btn lb-btn-secondary"
            href="messages.php">
            Back to Messages
        </a>

    </div>

</div>

</main>
</body>

</html>