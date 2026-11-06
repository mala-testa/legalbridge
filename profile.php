<?php
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='lb-error'><h3>You must be logged in.</h3></div>";
    echo "</main></body></html>";
    exit;
}

if (!verify_session_fingerprint(true)) {
    // optional: log the event
    lb_destroy_session();
    http_response_code(403);
    exit('Session integrity check failed. Please log in again.');
}

$user_id = (int) $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

echo "<h2 class='lb-title center'>Your Profile</h2>";

// Load user
$userStmt = dbSelect(
    "SELECT name, email FROM users WHERE id = :id",
    [':id' => $user_id]
);
$userRow = $userStmt->fetch();

$name  = $userRow['name'] ?? '';
$email = $userRow['email'] ?? '';

// Lawyer-specific fields
$bio  = '';
$rate = '';

if ($role === 'lawyer') {
    $lawStmt = dbSelect(
        "SELECT hourly_rate, bio FROM lawyers WHERE user_id = :uid",
        [':uid' => $user_id]
    );
    $lawRow = $lawStmt->fetch();
    if ($lawRow) {
        $rate = $lawRow['hourly_rate'] ?? '';
        $bio  = $lawRow['bio'] ?? '';
    }
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = sanitizeString($_POST['name'] ?? '');

    if ($role === 'lawyer') {
        $rate = sanitizeString($_POST['rate'] ?? '');
        $bio  = sanitizeString($_POST['bio'] ?? '');
    }

    if ($name === "") {
        $error = "Name is required.";
    } else {

        dbExecute(
            "UPDATE users SET name = :name WHERE id = :id",
            [
                ':name' => $name,
                ':id'   => $user_id
            ]
        );

        if ($role === 'lawyer') {
            dbExecute(
                "UPDATE lawyers SET hourly_rate = :rate, bio = :bio WHERE user_id = :uid",
                [
                    ':rate' => $rate,
                    ':bio'  => $bio,
                    ':uid'  => $user_id
                ]
            );
        }

        $error = "Profile updated.";
    }
}

$safeName  = e($name);
$safeEmail = e($email);
$safeRate  = e($rate);
$safeBio   = e($bio);

/*
Session ownership: $_SESSION['pending_reset_id'] 
ties the reset to the requesting session. Only that session can complete the reset.

Require current password on both request and final 
confirmation to reduce social engineering risk.

Single-use & expiry: mark used = 1 after success and set a short expiry (10 minutes).

Rate limiting: implement per-user and per-IP rate limits 
on request_reset.php and code verification attempts.

No logging of codes: do not write the code to logs or 
include it in admin messages beyond internal messages stored in DB 
(avoid exposing codes in admin UI unless admin is authorized).

Regenerate session id and recreate fingerprint after password change to prevent fixation and reuse.

Audit: admin receives a message that a reset was requested.
*/

?>

<div class="lb-form-container">

    <?php if ($error): ?>
        <div class="lb-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="profile.php" class="lb-form">

        <label for="name">Name</label>
        <input type="text"
            id="name"
            name="name"
            value="<?= $safeName ?>"
            required>

        <label for="email">Email</label>
        <input type="email"
            id="email"
            value="<?= $safeEmail ?>"
            disabled>

        <?php if ($role === 'lawyer'): ?>

            <label for="rate">Hourly Rate</label>
            <input type="number"
                step="0.01"
                id="rate"
                name="rate"
                value="<?= $safeRate ?>">

            <label for="bio">Bio</label>
            <textarea id="bio"
                name="bio"
                rows="5"><?= $safeBio ?></textarea>

        <?php endif; ?>

        <button type="submit" class="lb-btn">Save Profile</button>

    </form>

    <form method="post" action="request_reset.php" style="margin-top:1rem;">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
        <label for="confirm_password" style="display:block;margin-top:8px;">Enter current password to request reset</label>
        <input type="password" id="confirm_password" name="password" maxlength="64" required>
        <button type="submit" class="lb-btn lb-btn-secondary">Request Password Reset</button>
    </form>


</div>

</main>
</body>

</html>

