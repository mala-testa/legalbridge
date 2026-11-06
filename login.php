<?php
require_once __DIR__ . '/includes/header.php';

$error = '';
$email = '';
$pass  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $email = filter_var($email, FILTER_VALIDATE_EMAIL) ?: '';

    $pass  = $_POST['pass'] ?? '';

    if ($email === '' || $pass === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = dbSelect(
            "SELECT id, role, email, password_hash, active FROM users WHERE email = :email LIMIT 1",
            [':email' => $email]
        );

        if ($stmt->rowCount() === 0) {
            $error = 'Invalid login attempt.';
        } else {
            $row = $stmt->fetch();
            if ((int)$row['active'] !== 1) {
                $error = 'Account is inactive.';
            } elseif (!password_verify($pass, $row['password_hash'])) {
                $error = 'Invalid login attempt.';
            } else {
                if (needsRehash($row['password_hash'])) {
                    dbExecute(
                        "UPDATE users SET password_hash = :hash WHERE id = :id",
                        [':hash' => password_hash($pass, PASSWORD_DEFAULT), ':id' => $row['id']]
                    );
                }

                // Create session and set auth values
                lb_secure_session_start();

                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['email']   = $row['email'];
                $_SESSION['role']    = $row['role'];

                // Prevent fixation
                lb_regenerate_session();

                //Set fingerprint and create timestamp using new session id
                $_SESSION['fingerprint'] = hash(
                    'sha256',
                    $_SERVER['REMOTE_ADDR'] .
                        $_SERVER['HTTP_USER_AGENT'] .
                        session_id()
                );

                $_SESSION['created'] = time();

                $redirect = ($row['role'] === 'admin')
                    ? 'admin_dashboard.php'
                    : 'dashboard.php';

                header("Location: $redirect");

                exit;
            }
        }
    }
}


?>

<h2 class="lb-title center">Log In</h2>

<div class="lb-form-container">
    <?php if ($error): ?>
        <div class="lb-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" class="lb-form">

        <label for="email">Email</label>
        <input type="email"
            id="email"
            name="email"
            maxlength="255"
            value="<?= htmlspecialchars($email) ?>"
            required>

        <label for="pass">Password</label>
        <input type="password"
            id="pass"
            name="pass"
            maxlength="64"
            required>

        <button type="submit" class="lb-btn">Login</button>
    </form>
</div>

</main>
</body>

</html>