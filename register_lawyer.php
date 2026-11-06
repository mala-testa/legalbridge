<?php
require_once __DIR__. '/includes/header.php';

$error = "";
$email = "";
$pass = "";
$bio = "";
$rate = "0.00";

// If logged in, destroy session before registration
if (isset($_SESSION['user_id'])) {
    lb_destroy_session();
}

if (!verify_session_fingerprint(true)) {
    // optional: log the event
    lb_destroy_session();
    http_response_code(403);
    exit('Session integrity check failed. Please log in again.');
}

// Load specialties
$specialties = dbSelect("SELECT id, name FROM specialties WHERE active = 1 ORDER BY name");

echo "<script src='resources/js/script.js'></script>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = sanitizeString($_POST['email']);
    $pass  = $_POST['pass']; // do NOT sanitize passwords
    $specialty_id = (int) $_POST['specialty'];
    $rate = sanitizeString($_POST['rate']);
    $bio  = sanitizeString($_POST['bio']);

    if ($email === "" || $pass === "" || $specialty_id === 0) {
        $error = "All required fields must be entered.<br><br>";
    } else {

        // Check if email exists
        $stmt = dbSelect(
            "SELECT id FROM users WHERE email = :email",
            [':email' => $email]
        );

        if ($stmt->rowCount() > 0) {
            $error = "That email is already registered.<br><br>";
        } else {

            $hash = password_hash($pass, PASSWORD_DEFAULT);

            // Insert lawyer user
            dbExecute(
                "INSERT INTO users (role, name, email, password_hash)
                 VALUES ('lawyer', '', :email, :hash)",
                [
                    ':email' => $email,
                    ':hash'  => $hash
                ]
            );

            $user_id = $pdo->lastInsertId();

            // Insert lawyer details
            dbExecute(
                "INSERT INTO lawyers (user_id, specialty_id, hourly_rate, bio)
                 VALUES (:uid, :spec, :rate, :bio)",
                [
                    ':uid'  => $user_id,
                    ':spec' => $specialty_id,
                    ':rate' => $rate,
                    ':bio'  => $bio
                ]
            );

            die("<h4>Lawyer account created</h4>Please log in.</div></body></html>");
        }
    }
}

echo "<form method='post' action='register_lawyer.php'>$error";

echo "
    <div data-role='fieldcontain'>
        <label>Email</label>
        <input type='email' name='email' value='$email' onBlur='checkEmail(this)'>
        <div id='used'>&nbsp;</div>
    </div>

    <div data-role='fieldcontain'>
        <label>Password</label>
        <input type='password' name='pass'>
    </div>

    <div data-role='fieldcontain'>
        <label>Specialty</label>
        <select name='specialty'>
";

while ($row = $specialties->fetch()) {
    echo "<option value='{$row['id']}'>{$row['name']}</option>";
}

echo "
        </select>
    </div>

    <div data-role='fieldcontain'>
        <label>Hourly Rate</label>
        <input type='number' step='0.01' name='rate' value='$rate'>
    </div>

    <div data-role='fieldcontain'>
        <label>Bio</label>
        <textarea name='bio'>$bio</textarea>
    </div>

    <div data-role='fieldcontain'>
        <input data-transition='slide' type='submit' value='Register as Lawyer'>
    </div>

</form>

</div>
</body>
</html>
";
?>
