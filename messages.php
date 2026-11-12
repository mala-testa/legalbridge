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
$role    = $_SESSION['role'] ?? '';

if (!in_array($role, ['admin', 'lawyer', 'client'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$error   = '';
$success = '';

$recipient_email = '';
$message_text    = '';

/*
|--------------------------------------------------------------------------
| Default message
|--------------------------------------------------------------------------
*/

function defaultMessage(
    string $role,
    string $user_email
): string {
    switch ($role) {
        case 'client':
            return
                "Hello,\n\n"
                . "I hope you are doing well. I am reaching out "
                . "regarding my case and would appreciate an update "
                . "when possible.\n\n"
                . "Thank you,\n"
                . $user_email;

        case 'lawyer':
            return
                "Hello,\n\n"
                . "I hope this message finds you well. I am contacting "
                . "you regarding your case and would like to discuss "
                . "the next steps at your convenience.\n\n"
                . "Best regards,\n"
                . $user_email;

        case 'admin':
            return
                "Hello,\n\n"
                . "This is a message from the LegalBridge administration "
                . "team. Please review the following information.\n\n"
                . "Thank you,\n"
                . "LegalBridge Admin ("
                . $user_email
                . ")";

        default:
            return
                "Hello,\n\n"
                . "Thank you,\n"
                . $user_email;
    }
}

/*
|--------------------------------------------------------------------------
| Load current user from database
|--------------------------------------------------------------------------
|
| Use the database as the authoritative source instead of relying only on
| the email stored in the session.
|
*/

$userStmt = dbSelect(
    "SELECT id, email, role
     FROM users
     WHERE id = :user_id
       AND active = 1
     LIMIT 1",
    [
        ':user_id' => $user_id
    ]
);

$currentUser = $userStmt->fetch();

if (!$currentUser) {
    lb_destroy_session();

    http_response_code(403);

    exit('Your account could not be verified.');
}

$user_email = $currentUser['email'];
$role       = $currentUser['role'];

/*
|--------------------------------------------------------------------------
| Process new message
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_email =
        strtolower(trim($_POST['to'] ?? ''));

    $message_text =
        trim($_POST['message'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | CSRF validation
    |--------------------------------------------------------------------------
    */

    if (!csrfValidate($_POST['csrf'] ?? '')) {
        $error =
            'Invalid request. Please refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Recipient validation
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $recipient_email === ''
    ) {
        $error = 'Recipient email is required.';
    }

    if (
        $error === '' &&
        strlen($recipient_email) > 255
    ) {
        $error = 'Recipient email is too long.';
    }

    if (
        $error === '' &&
        !filter_var(
            $recipient_email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $error = 'Please enter a valid recipient email address.';
    }

    /*
    |--------------------------------------------------------------------------
    | Message validation
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        $message_text === ''
    ) {
        $error = 'Message is required.';
    }

    if (
        $error === '' &&
        mb_strlen($message_text) > 5000
    ) {
        $error = 'Message cannot exceed 5,000 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Find active recipient
    |--------------------------------------------------------------------------
    */

    if ($error === '') {
        $recipientStmt = dbSelect(
            "SELECT id, email
             FROM users
             WHERE email = :email
               AND active = 1
             LIMIT 1",
            [
                ':email' => $recipient_email
            ]
        );

        $recipient = $recipientStmt->fetch();

        if (!$recipient) {
            $error =
                'No active user was found with that email address.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent sending a message to yourself
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        (int)$recipient['id'] === $user_id
    ) {
        $error = 'You cannot send a message to yourself.';
    }

    /*
    |--------------------------------------------------------------------------
    | Save message
    |--------------------------------------------------------------------------
    |
    | This matches your current messages table:
    |
    | sender_id
    | recipient_id
    | message
    | created_at
    |
    */

    if ($error === '') {
        try {
            $inserted = dbExecute(
                "INSERT INTO messages
                (
                    sender_id,
                    recipient_id,
                    message
                )
                VALUES
                (
                    :sender_id,
                    :recipient_id,
                    :message
                )",
                [
                    ':sender_id'    => $user_id,
                    ':recipient_id' => (int)$recipient['id'],
                    ':message'      => $message_text
                ]
            );

            if (!$inserted) {
                throw new RuntimeException(
                    'Message insert failed.'
                );
            }

            $success = 'Message sent successfully.';

            $recipient_email = '';
            $message_text    = '';
        } catch (Throwable $exception) {
            lb_log_error(
                'Message send failed for user ID '
                    . $user_id
                    . ': '
                    . $exception->getMessage()
            );

            $error =
                'Your message could not be sent. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch inbox
|--------------------------------------------------------------------------
*/

$inbox = dbSelect(
    "SELECT
        m.id,
        m.message,
        m.created_at,
        sender.name AS sender_name,
        sender.email AS sender_email
     FROM messages m
     INNER JOIN users sender
        ON m.sender_id = sender.id
     WHERE m.recipient_id = :user_id
     ORDER BY m.created_at DESC, m.id DESC",
    [
        ':user_id' => $user_id
    ]
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Fetch sent messages
|--------------------------------------------------------------------------
*/

$sent = dbSelect(
    "SELECT
        m.id,
        m.message,
        m.created_at,
        recipient.name AS recipient_name,
        recipient.email AS recipient_email
     FROM messages m
     INNER JOIN users recipient
        ON m.recipient_id = recipient.id
     WHERE m.sender_id = :user_id
     ORDER BY m.created_at DESC, m.id DESC",
    [
        ':user_id' => $user_id
    ]
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Default message shown only when needed
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    (
        $success !== '' &&
        $message_text === ''
    )
) {
    $message_text =
        defaultMessage($role, $user_email);
}

/*
|--------------------------------------------------------------------------
| Load HTML header after security checks and POST processing
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    Messages
</h2>

<div class="lb-form-container">

    <?php if ($error !== ''): ?>

        <div class="lb-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($success !== ''): ?>

        <div class="lb-success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>

    <form
        method="post"
        action="messages.php"
        class="lb-form">

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrfToken()) ?>">

        <label for="from_email">
            From
        </label>

        <input
            type="email"
            id="from_email"
            value="<?= e($user_email) ?>"
            readonly>

        <label for="to">
            To (email)
        </label>

        <input
            type="email"
            id="to"
            name="to"
            maxlength="255"
            value="<?= e($recipient_email) ?>"
            autocomplete="email"
            required>

        <label for="message">
            Message
        </label>

        <textarea
            id="message"
            name="message"
            rows="7"
            maxlength="5000"
            required><?= e($message_text) ?></textarea>

        <button
            type="submit"
            class="lb-btn">
            Send Message
        </button>

    </form>

</div>

<h3 class="center">
    Inbox
</h3>

<div class="specialties-table-wrapper">

    <?php if (empty($inbox)): ?>

        <div class="lb-card">
            <p>Your inbox is empty.</p>
        </div>

    <?php else: ?>

        <table class="specialties-table">

            <thead>
                <tr>
                    <th>From</th>
                    <th>Message</th>
                    <th>Date</th>
                    <th style="width: 180px;">Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($inbox as $message): ?>

                    <?php
                    $message_id =
                        (int)$message['id'];

                    $preview =
                        mb_substr(
                            $message['message'] ?? '',
                            0,
                            60
                        );

                    $message_length =
                        mb_strlen(
                            $message['message'] ?? ''
                        );

                    $created_timestamp =
                        strtotime(
                            $message['created_at'] ?? ''
                        );

                    $created_date =
                        $created_timestamp !== false
                        ? date(
                            'M j, Y g:i A',
                            $created_timestamp
                        )
                        : '';
                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= e(
                                    $message['sender_name']
                                        ?? 'Unknown'
                                ) ?>
                            </strong>

                            <br>

                            <?= e(
                                $message['sender_email']
                                    ?? ''
                            ) ?>
                        </td>

                        <td>
                            <?= nl2br(e($preview)) ?>

                            <?php if ($message_length > 60): ?>
                                ...
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e($created_date) ?>
                        </td>

                        <td class="actions-cell">

                            <a
                                class="action-btn"
                                href="view_message.php?id=<?= $message_id ?>">
                                View
                            </a>

                            <a
                                class="action-btn"
                                href="forward_message.php?id=<?= $message_id ?>">
                                Forward
                            </a>

                            <form
                                method="post"
                                action="delete_message.php">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $message_id ?>">

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrfToken()) ?>">

                                <button
                                    type="submit"
                                    class="action-btn danger"
                                    onclick="return confirm('Delete this message?')">
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</div>

<h3 class="center">
    Sent Messages
</h3>

<div class="specialties-table-wrapper">

    <?php if (empty($sent)): ?>

        <div class="lb-card">
            <p>You have not sent any messages.</p>
        </div>

    <?php else: ?>

        <table class="specialties-table">

            <thead>
                <tr>
                    <th>To</th>
                    <th>Message</th>
                    <th>Date</th>
                    <th style="width: 180px;">Actions</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($sent as $message): ?>

                    <?php
                    $message_id =
                        (int)$message['id'];

                    $preview =
                        mb_substr(
                            $message['message'] ?? '',
                            0,
                            60
                        );

                    $message_length =
                        mb_strlen(
                            $message['message'] ?? ''
                        );

                    $created_timestamp =
                        strtotime(
                            $message['created_at'] ?? ''
                        );

                    $created_date =
                        $created_timestamp !== false
                        ? date(
                            'M j, Y g:i A',
                            $created_timestamp
                        )
                        : '';
                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= e(
                                    $message['recipient_name']
                                        ?? 'Unknown'
                                ) ?>
                            </strong>

                            <br>

                            <?= e(
                                $message['recipient_email']
                                    ?? ''
                            ) ?>
                        </td>

                        <td>
                            <?= nl2br(e($preview)) ?>

                            <?php if ($message_length > 60): ?>
                                ...
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e($created_date) ?>
                        </td>

                        <td class="actions-cell">

                            <a
                                class="action-btn"
                                href="view_message.php?id=<?= $message_id ?>">
                                View
                            </a>

                            <a
                                class="action-btn"
                                href="forward_message.php?id=<?= $message_id ?>">
                                Forward
                            </a>

                            <form
                                method="post"
                                action="delete_message.php">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $message_id ?>">

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrfToken()) ?>">

                                <button
                                    type="submit"
                                    class="action-btn danger"
                                    onclick="return confirm('Delete this message?')">
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</div>

</main>
</body>

</html>