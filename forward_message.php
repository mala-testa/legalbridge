<?php
require_once __DIR__ . '/includes/header.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);

$stmt = dbSelect(
    "SELECT id, auth, recip, message FROM messages WHERE id = :id",
    [':id' => $id]
);

$msg = $stmt->fetch();
if (!$msg) {
    exit("Message not found.");
}

$email = $_SESSION['email'] ?? null;

// Ensure logged-in user is sender or recipient
if ($msg['auth'] !== $email && $msg['recip'] !== $email) {
    exit("Unauthorized access.");
}
?>

<h2 class="lb-title center">Forward Message</h2>

<div class="lb-form-container">

    <form method="post" action="messages.php" class="lb-form">

        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

        <label>Forward To (email)</label>
        <input type="email" name="to" maxlength="255" required>
        <input type="hidden" name="forwarded_from" value="<?= e($msg['auth']) ?>">

        <label>Message</label>
        <textarea name="message" rows="7" maxlength="5000" required>
        Forwarded message:

        From: <?= e($msg['auth']) ?>

        <?= e($msg['message']) ?>
        </textarea>

        <button type="submit" class="lb-btn">Send Forward</button>
    </form>
</div>

</main>
</body>

</html>