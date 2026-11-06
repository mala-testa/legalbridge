<?php
require_once __DIR__ . '/includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='lb-error'><h3>You must be logged in.</h3></div>";
    echo "</main></body></html>";
    exit;
}

if (!verify_session_fingerprint(true)) {
    lb_destroy_session();
    http_response_code(403);
    exit('Session integrity check failed. Please log in again.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Show a simple form if needed (or redirect back to profile)
    header('Location: profile.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$email = filter_var($email, FILTER_VALIDATE_EMAIL) ?: '';
$pass  = $_POST['password'] ?? '';

if ($email === '' || $pass === '') {
    $error = "Email and current password are required.";
} else {
    // Fetch user
    $stmt = dbSelect("SELECT id, password_hash FROM users WHERE email = :email LIMIT 1", [':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        $error = "Invalid email or password.";
    } else {
        // User has already proven identity with current password
        $_SESSION['password_change_verified'] = $user['id'];

        header('Location: reset_password.php');
        exit;

    }
}

// If we reach here, show error and link back
?>
<h2 class="lb-title center">Request Password Reset</h2>
<div class="lb-form-container">
    <?php if ($error): ?>
        <div class="lb-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <p><a class="lb-btn" href="profile.php">Back to profile</a></p>
</div>
</main>
</body>

</html>