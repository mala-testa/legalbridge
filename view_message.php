<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);

// Fetch message AND verify ownership
$stmt = dbSelect(
    "SELECT * FROM messages WHERE id = :id",
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

<h2 class="lb-title center">Message</h2>

<div class="lb-card">
    <p><strong>From:</strong> <?= e($msg['auth']) ?></p>
    <p><strong>To:</strong> <?= e($msg['recip']) ?></p>
    <p><strong>Date:</strong> <?= date('Y-m-d H:i', $msg['time']) ?></p>
    <p><strong>Message:</strong></p>
    <p><?= nl2br(e($msg['message'])) ?></p>

    <a class="lb-btn" href="forward_message.php?id=<?= $msg['id'] ?>">Forward</a>

    <form method="post" action="delete_message.php" style="display:inline;">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
        <button type="submit" class="lb-btn lb-danger">Delete</button>
    </form>
</div>
</main>
</body>

</html>