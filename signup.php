<?php
require_once __DIR__ . '/includes/functions.php';
require_once 'includes/header.php';

$error = "";
$name = "";
$email = "";
$pass  = "";

// If logged in, destroy session before signup
if (isset($_SESSION['user_id'])) {
    lb_destroy_session();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['pass'];

    if ($name === "" || $email === "" || $pass === "") {
        $error = "All fields are required.";
    } elseif (strlen($name) > 100) {
        $error = 'Name cannot exceed 100 characters.';
    } elseif (!preg_match("/^[\p{L}\s'.\-]+$/u", $name)) {

        $error = 'Please enter a valid name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif (strlen($pass) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {

        // Check if email exists
        $stmt = dbSelect(
            "SELECT id FROM users WHERE email = :email LIMIT 1",
            [':email' => $email]
        );

        if ($stmt->rowCount() > 0) {
            $error = "That email is already registered.";
        } else {

            $hash = password_hash($pass, PASSWORD_DEFAULT);

            // Insert client
            dbExecute(
                "INSERT INTO users (role, name, email, password_hash)
                    VALUES ('client', :name, :email, :hash)",
                [
                    ':name' => $name,
                    ':email' => $email,
                    ':hash'  => $hash
                ]
            );

            header('Location: login.php?created=1');
            exit;
        }
    }
}

?>

<h2 class="lb-title center">Sign Up</h2>

<div class="lb-form-container">

    <?php if ($error): ?>
        <div class="lb-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8')  ?></div>
    <?php endif; ?>

    <form method="post" action="signup.php" class="lb-form">

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrfToken()) ?>">

        <label for="name">Name</label>

        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
            required>

        <label for="email">Email</label>
        <input type="email"
            id="email"
            name="email"
            maxlength="255"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            required>

        <label for="pass">Password</label>
        <input type="password"
            id="pass"
            name="pass"
            maxlength="128"
            required
            aria-describedby="passwordHelp">

        <button type="submit" class="lb-btn">Sign Up</button>
    </form>

</div>

</main>
</body>

</html>