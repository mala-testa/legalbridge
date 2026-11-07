<?php
require_once __DIR__. '/includes/auth.php';
require_once __DIR__. '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {

    // Sanitize and validate email
    $raw = $_POST['email'] ?? '';
    $email = filter_var(sanitizeString($raw), FILTER_VALIDATE_EMAIL);

    if ($email === false || $email === '') {
        echo "<span class='error'>&nbsp;&#x2718; Invalid email address</span>";
        exit;
    }

    // Prepared statement — safe & modern
    $stmt = dbSelect(
        "SELECT id FROM users WHERE email = :email",
        [':email' => $email]
    );

    if ($stmt->rowCount() > 0) {
        echo "<span class='taken'>&nbsp;&#x2718; The email '" . e($email) . "' is already registered</span>";
    } else {
        echo "<span class='available'>&nbsp;&#x2714; The email '" . e($email) . "' is available</span>";
    }
}
