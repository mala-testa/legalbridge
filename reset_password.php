<?php
require_once __DIR__ . '/includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='lb-error'><h3>You must be logged in.</h3></div>";
    echo "</main></body></html>";
    exit;
}

// Require fingerprint for this sensitive flow
if (!verify_session_fingerprint(true)) {
    lb_destroy_session();
    http_response_code(403);
    exit('Session integrity check failed. Please log in again.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current = $_POST['current_password'] ?? '';
    $newpass = $_POST['new_password'] ?? '';

    if ($current === '' || $newpass === '') {

        $error = "Current password and new password are required.";

    } else {

        $stmt = dbSelect(
            "SELECT id, password_hash
             FROM users
             WHERE id = :id
             LIMIT 1",
            [':id' => $_SESSION['user_id']]
        );

        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {

            $error = "Invalid current password.";

        } elseif (password_verify($newpass, $user['password_hash'])) {

            $error = "New password must be different from your current password.";

        } else {

            dbExecute(
                "UPDATE users
                 SET password_hash = :hash
                 WHERE id = :id",
                [
                    ':hash' => password_hash($newpass, PASSWORD_DEFAULT),
                    ':id'   => $user['id']
                ]
            );

            // Remove temporary password change authorization
            unset($_SESSION['password_change_verified']);
            unset($_SESSION['password_change_expires']);

            // Rotate session
            lb_regenerate_session();

            $_SESSION['fingerprint'] = hash(
                'sha256',
                ($_SERVER['REMOTE_ADDR'] ?? '') .
                ($_SERVER['HTTP_USER_AGENT'] ?? '') .
                session_id()
            );

            $_SESSION['created'] = time();

            $success = "Password updated successfully.";
        }
    }
}

?>
<h2 class="lb-title center">Reset Password</h2>

<div class="lb-form-container">
    <?php if ($error): ?>
        <div class="lb-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="lb-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <p><a class="lb-btn" href="dashboard.php">Return to dashboard</a></p>
    <?php else: ?>
        <form method="post" action="reset_password.php" class="lb-form">

            <label for="current_password">
                Current Password
            </label>

            <input
                type="password"
                id="current_password"
                name="current_password"
                maxlength="64"
                required>

            <label for="new_password">
                New Password
            </label>

            <input
                type="password"
                id="new_password"
                name="new_password"
                maxlength="64"
                required>

            <button type="submit" class="lb-btn">
                Change Password
            </button>

        </form>
    <?php endif; ?>
</div>

</main>
</body>

</html>