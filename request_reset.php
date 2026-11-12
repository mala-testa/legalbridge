<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

$error = '';

/*
|--------------------------------------------------------------------------
| Require authenticated user
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
| Accept POST requests only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF validation
|--------------------------------------------------------------------------
*/

if (!csrfValidate($_POST['csrf'] ?? '')) {
    http_response_code(403);

    $error = 'Invalid request. Please return to your profile and try again.';
}

/*
|--------------------------------------------------------------------------
| Validate submitted password
|--------------------------------------------------------------------------
*/

$password = $_POST['password'] ?? '';

if ($error === '' && $password === '') {
    $error = 'Your current password is required.';
}

if (
    $error === '' &&
    strlen($password) > 128
) {
    $error = 'Invalid password.';
}

/*
|--------------------------------------------------------------------------
| Load the authenticated account
|--------------------------------------------------------------------------
|
| Do not trust a posted email address. The account is taken directly from
| the authenticated session.
|
*/

if ($error === '') {
    $stmt = dbSelect(
        "SELECT id, password_hash
         FROM users
         WHERE id = :id
           AND active = 1
         LIMIT 1",
        [
            ':id' => $user_id
        ]
    );

    $user = $stmt->fetch();

    if (!$user) {
        lb_log_error(
            'Password reset request failed: active user not found for ID '
                . $user_id
        );

        $error = 'Your account could not be verified.';
    }
}

/*
|--------------------------------------------------------------------------
| Verify the current password
|--------------------------------------------------------------------------
*/

if (
    $error === '' &&
    !password_verify(
        $password,
        $user['password_hash']
    )
) {
    $error = 'The current password is incorrect.';
}

/*
|--------------------------------------------------------------------------
| Mark this session as verified
|--------------------------------------------------------------------------
|
| reset_password.php must verify both values and enforce the 10-minute
| expiry before allowing the password to be changed.
|
*/

if ($error === '') {
    $_SESSION['password_change_verified'] =
        (int)$user['id'];

    $_SESSION['password_change_verified_at'] =
        time();

    /*
     * Do not store the submitted password in the session.
     * It is needed only for this verification.
     */

    unset($password);

    header('Location: reset_password.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Render error page
|--------------------------------------------------------------------------
|
| Load header.php only after redirects have already been handled.
|
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    Request Password Reset
</h2>

<div class="lb-form-container">

    <?php if ($error !== ''): ?>

        <div class="lb-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <p>
        <a
            class="lb-btn"
            href="profile.php">
            Back to Profile
        </a>
    </p>

</div>

</main>
</body>

</html>