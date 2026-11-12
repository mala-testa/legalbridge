<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

$error   = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Require authenticated user
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Verify session fingerprint
|--------------------------------------------------------------------------
*/

if (!verify_session_fingerprint(true)) {
    lb_destroy_session();

    http_response_code(403);

    exit('Session integrity check failed. Please log in again.');
}

/*
|--------------------------------------------------------------------------
| Verify temporary password-change authorization
|--------------------------------------------------------------------------
|
| request_reset.php should set:
|
| $_SESSION['password_change_verified']
| $_SESSION['password_change_verified_at']
|
| The authorization is valid for 10 minutes.
|
*/

$verified_user_id =
    (int)($_SESSION['password_change_verified'] ?? 0);

$verified_at =
    (int)($_SESSION['password_change_verified_at'] ?? 0);

$authorization_expired =
    $verified_at <= 0 ||
    (time() - $verified_at) > 600;

if (
    $verified_user_id !== $user_id ||
    $authorization_expired
) {
    unset(
        $_SESSION['password_change_verified'],
        $_SESSION['password_change_verified_at']
    );

    header('Location: profile.php?reset_expired=1');
    exit;
}

/*
|--------------------------------------------------------------------------
| Process password change
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password =
        $_POST['current_password'] ?? '';

    $new_password =
        $_POST['new_password'] ?? '';

    $confirm_password =
        $_POST['confirm_password'] ?? '';

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
    | Required field validation
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        (
            $current_password === '' ||
            $new_password === '' ||
            $confirm_password === ''
        )
    ) {
        $error =
            'Current password, new password, and password confirmation are required.';
    }

    /*
    |--------------------------------------------------------------------------
    | Password length validation
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        strlen($current_password) > 128
    ) {
        $error = 'Invalid current password.';
    }

    if (
        $error === '' &&
        strlen($new_password) > 128
    ) {
        $error =
            'New password cannot exceed 128 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | New password strength validation
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        (
            strlen($new_password) < 8 ||
            !preg_match('/[A-Z]/', $new_password) ||
            !preg_match('/[a-z]/', $new_password) ||
            !preg_match('/[0-9]/', $new_password)
        )
    ) {
        $error =
            'New password must contain at least 8 characters, one uppercase letter, one lowercase letter, and one number.';
    }

    /*
    |--------------------------------------------------------------------------
    | Password confirmation
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        !hash_equals(
            $new_password,
            $confirm_password
        )
    ) {
        $error =
            'New password and confirmation do not match.';
    }

    /*
    |--------------------------------------------------------------------------
    | Load authenticated account
    |--------------------------------------------------------------------------
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
                'Password change failed: active user not found for ID '
                    . $user_id
            );

            $error =
                'Your account could not be verified.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Verify current password again
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        !password_verify(
            $current_password,
            $user['password_hash']
        )
    ) {
        $error = 'The current password is incorrect.';
    }

    /*
    |--------------------------------------------------------------------------
    | Ensure new password is different
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        password_verify(
            $new_password,
            $user['password_hash']
        )
    ) {
        $error =
            'New password must be different from your current password.';
    }

    /*
    |--------------------------------------------------------------------------
    | Update password
    |--------------------------------------------------------------------------
    */

    if ($error === '') {
        try {
            $new_password_hash =
                password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

            if ($new_password_hash === false) {
                throw new RuntimeException(
                    'Password hashing failed.'
                );
            }

            $updated = dbExecute(
                "UPDATE users
                 SET password_hash = :password_hash
                 WHERE id = :id
                   AND active = 1",
                [
                    ':password_hash' => $new_password_hash,
                    ':id'            => $user_id
                ]
            );

            if (!$updated) {
                throw new RuntimeException(
                    'Password update query failed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Remove temporary authorization
            |--------------------------------------------------------------------------
            |
            | This makes the password-change authorization single-use.
            |
            */

            unset(
                $_SESSION['password_change_verified'],
                $_SESSION['password_change_verified_at']
            );

            /*
            |--------------------------------------------------------------------------
            | Regenerate the session
            |--------------------------------------------------------------------------
            */

            lb_regenerate_session();

            $_SESSION['fingerprint'] = hash(
                'sha256',
                ($_SERVER['REMOTE_ADDR'] ?? '') .
                    ($_SERVER['HTTP_USER_AGENT'] ?? '') .
                    session_id()
            );

            $_SESSION['created'] = time();

            /*
            |--------------------------------------------------------------------------
            | Rotate CSRF token
            |--------------------------------------------------------------------------
            */

            $_SESSION['csrf'] =
                bin2hex(random_bytes(32));

            /*
            |--------------------------------------------------------------------------
            | Clear password variables
            |--------------------------------------------------------------------------
            */

            $current_password = '';
            $new_password     = '';
            $confirm_password = '';

            $success =
                'Password updated successfully.';
        } catch (Throwable $exception) {
            lb_log_error(
                'Password update failed for user ID '
                    . $user_id
                    . ': '
                    . $exception->getMessage()
            );

            $error =
                'Your password could not be updated. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load HTML header after redirects and POST processing
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    Reset Password
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

        <p>
            <a
                class="lb-btn"
                href="dashboard.php">
                Return to Dashboard
            </a>
        </p>

    <?php else: ?>

        <form
            method="post"
            action="reset_password.php"
            class="lb-form">

            <input
                type="hidden"
                name="csrf"
                value="<?= e(csrfToken()) ?>">

            <label for="current_password">
                Current Password
            </label>

            <input
                type="password"
                id="current_password"
                name="current_password"
                maxlength="128"
                autocomplete="current-password"
                required>

            <label for="new_password">
                New Password
            </label>

            <input
                type="password"
                id="new_password"
                name="new_password"
                minlength="8"
                maxlength="128"
                autocomplete="new-password"
                aria-describedby="password_requirements"
                required>

            <small id="password_requirements">
                Use at least 8 characters, including one uppercase letter,
                one lowercase letter, and one number.
            </small>

            <label for="confirm_password">
                Confirm New Password
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                maxlength="128"
                autocomplete="new-password"
                required>

            <button
                type="submit"
                class="lb-btn">
                Change Password
            </button>

        </form>

        <p style="margin-top: 1rem;">
            <a
                class="lb-btn lb-btn-secondary"
                href="profile.php">
                Cancel
            </a>
        </p>

    <?php endif; ?>

</div>

</main>
</body>

</html>