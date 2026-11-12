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

    exit(
        'Session integrity check failed. Please log in again.'
    );
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
| The signed-in user must be either the sender or recipient.
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
        ':message_id'        => $message_id,
        ':sender_user_id'    => $user_id,
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
| Format original message date
|--------------------------------------------------------------------------
*/

$created_timestamp = strtotime(
    $message['created_at'] ?? ''
);

$created_date = $created_timestamp !== false
    ? date('F j, Y, g:i A', $created_timestamp)
    : 'Unknown date';

/*
|--------------------------------------------------------------------------
| Create forwarded message text
|--------------------------------------------------------------------------
|
| The original sender details come from the database. No hidden
| forwarded_from value is needed or trusted.
|
*/

$forwarded_message =
    "---------- Forwarded message ----------\n"
    . "From: "
    . ($message['sender_name'] ?? 'Unknown')
    . " <"
    . ($message['sender_email'] ?? '')
    . ">\n"
    . "To: "
    . ($message['recipient_name'] ?? 'Unknown')
    . " <"
    . ($message['recipient_email'] ?? '')
    . ">\n"
    . "Date: "
    . $created_date
    . "\n\n"
    . ($message['message'] ?? '');

/*
|--------------------------------------------------------------------------
| Enforce messages.php maximum message length
|--------------------------------------------------------------------------
|
| Your corrected messages.php accepts no more than 5,000 characters.
| Leave sufficient room for the user to add comments before forwarding.
|
*/

if (mb_strlen($forwarded_message) > 4500) {
    $forwarded_message =
        mb_substr(
            $forwarded_message,
            0,
            4500
        );

    $forwarded_message .=
        "\n\n[Original message shortened for forwarding.]";
}

/*
|--------------------------------------------------------------------------
| Render page
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    Forward Message
</h2>

<div class="lb-form-container">

    <form
        method="post"
        action="messages.php"
        class="lb-form">

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrfToken()) ?>">

        <label for="to">
            Forward To (email)
        </label>

        <input
            type="email"
            id="to"
            name="to"
            maxlength="255"
            autocomplete="email"
            required>

        <label for="message">
            Message
        </label>

        <textarea
            id="message"
            name="message"
            rows="12"
            maxlength="5000"
            required><?= e($forwarded_message) ?></textarea>

        <button
            type="submit"
            class="lb-btn">
            Send Forward
        </button>

        <a
            class="lb-btn lb-btn-secondary"
            href="view_message.php?id=<?= $message_id ?>">
            Cancel
        </a>

    </form>

</div>

</main>
</body>
</html>