<?php
require_once __DIR__ . '/includes/header.php';

requireLogin();

$user_email = $_SESSION['email'];
$role = $_SESSION['role'];

$error = '';
$success = '';

function defaultMessage($role, $user_email)
{
    switch ($role) {
        case 'client':
            return "Hello,\n\nI hope you are doing well. I am reaching out regarding my case and would appreciate an update when possible.\n\nThank you,\n$user_email";
        case 'lawyer':
            return "Hello,\n\nI hope this message finds you well. I am contacting you regarding your case and would like to discuss the next steps at your convenience.\n\nBest regards,\n$user_email";
        case 'admin':
            return "Hello,\n\nThis is a message from the LegalBridge administration team. Please review the following information.\n\nThank you,\nLegalBridge Admin ($user_email)";
        default:
            return "Hello,\n\nThank you,\n$user_email";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrfValidate($_POST['csrf'] ?? null)) {

        $error = 'Invalid request.';
    } else {

        $to = strtolower(trim($_POST['to'] ?? ''));
        $msg = trim($_POST['message'] ?? '');

        if ($to === '' || $msg === '') {
            $error = "Recipient and message are required.";
        } else {

            $recipient = dbSelect(
                "SELECT id
                FROM users
                WHERE email = :email",
                [':email' => $to]
            )->fetch();

            if (!$recipient) {
                $error = 'Recipient email does not exist.';
            } else {

                dbExecute(
                    "INSERT INTO messages
                    (auth, recip, pm, time, message)
                    VALUES
                    (:auth, :recip, 'Y', :time, :msg)",
                    [
                        ':auth'  => $user_email,
                        ':recip' => $to,
                        ':time'  => time(),
                        ':msg'   => $msg
                    ]
                );

                $success = 'Message sent successfully.';
            }
        }
    }
}

/* Fetch inbox */
$inbox = dbSelect(
    "SELECT id, auth, message, time 
     FROM messages 
     WHERE recip = :email 
     ORDER BY time DESC",
    [':email' => $user_email]
)->fetchAll();

/* Fetch sent messages */
$sent = dbSelect(
    "SELECT id, recip, message, time 
     FROM messages 
     WHERE auth = :email 
     ORDER BY time DESC",
    [':email' => $user_email]
)->fetchAll();
?>

<h2 class="lb-title center">Messages</h2>

<div class="lb-form-container">

    <?php if ($error): ?>
        <div class="lb-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="lb-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="post" action="messages.php" class="lb-form">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

        <label>From</label>
        <input type="email" value="<?= e($user_email) ?>" readonly>

        <label>To (email)</label>
        <input type="email" name="to" required>

        <label>Message</label>
        <textarea name="message" rows="7" required><?= e(defaultMessage($role, $user_email)) ?></textarea>

        <button type="submit" class="lb-btn">Send Message</button>
    </form>
</div>

<h3 class="center">Inbox</h3>

<div class="specialties-table-wrapper">
    <table class="specialties-table">
        <thead>
            <tr>
                <th>From</th>
                <th>Message</th>
                <th style="width:180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inbox as $m): ?>
                <tr>
                    <td><?= e($m['auth']) ?></td>
                    <td><?= nl2br(e(substr($m['message'], 0, 60))) ?>...</td>
                    <td class="actions-cell">
                        <a class="action-btn" href="view_message.php?id=<?= $m['id'] ?>">View</a>
                        <a class="action-btn" href="forward_message.php?id=<?= $m['id'] ?>">Forward</a>
                        <form method="post" action="delete_message.php">

                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">

                            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

                            <button type="submit" class="action-btn danger"
                                onclick="return confirm('Delete this message?')">

                                Delete

                            </button>

                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h3 class="center">Sent Messages</h3>

<div class="specialties-table-wrapper">
    <table class="specialties-table">
        <thead>
            <tr>
                <th>To</th>
                <th>Message</th>
                <th style="width:180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sent as $m): ?>
                <tr>
                    <td><?= e($m['recip']) ?></td>
                    <td><?= nl2br(e(substr($m['message'], 0, 60))) ?>...</td>
                    <td class="actions-cell">
                        <a class="action-btn" href="view_message.php?id=<?= $m['id'] ?>">View</a>
                        <a class="action-btn" href="forward_message.php?id=<?= $m['id'] ?>">Forward</a>
                        <form method="post" action="delete_message.php">

                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">

                            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

                            <button type="submit" class="action-btn danger"
                                onclick="return confirm('Delete this message?')">

                                Delete

                            </button>

                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</main>
</body>

</html>