<?php
require_once __DIR__ . '/includes/functions.php';
require_once 'includes/auth.php';

lb_secure_session_start();

$error = '';
$name  = '';
$email = '';
$role  = '';

if (isset($_SESSION['user_id'])) {
    lb_destroy_session();
    lb_secure_session_start();
}

/*
|--------------------------------------------------------------------------
| Load specialties once
|--------------------------------------------------------------------------
*/

$specialties = dbSelect(
    "SELECT id, name
     FROM specialties
     WHERE active = 1
     ORDER BY name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['pass'] ?? '';
    $role  = $_POST['role'] ?? '';

    $hourly_rate      = trim($_POST['hourly_rate'] ?? '');
    $lawyer_specialty = (int)($_POST['lawyer_specialty'] ?? 0);

    $bio = trim($_POST['bio'] ?? '');
    $bio = preg_replace('/\s+/u', ' ', $bio);

    $client_specialty = (int)($_POST['client_specialty'] ?? 0);

    $case_description = trim($_POST['case_description'] ?? '');
    $case_description = preg_replace('/\s+/u', ' ', $case_description);

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (!csrfValidate($_POST['csrf'] ?? '')) {

        $error = 'Invalid request.';
    } elseif ($name === '' || $email === '' || $pass === '') {

        $error = 'All required fields must be completed.';
    } elseif (!in_array($role, ['lawyer', 'client'], true)) {

        $error = 'Please select an account type.';
    } elseif (mb_strlen($name) < 2) {

        $error = 'Name is too short.';
    } elseif (mb_strlen($name) > 100) {

        $error = 'Name cannot exceed 100 characters.';
    } elseif (!preg_match("/^[\p{L}\s'.\-]+$/u", $name)) {

        $error = 'Please enter a valid name.';
    } elseif (strlen($email) > 255) {

        $error = 'Email address is too long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please provide a valid email address.';
    } elseif (
        strlen($pass) < 8 ||
        !preg_match('/[A-Z]/', $pass) ||
        !preg_match('/[a-z]/', $pass) ||
        !preg_match('/[0-9]/', $pass)
    ) {

        $error = 'Password must contain at least 8 characters, one uppercase letter, one lowercase letter and one number.';
    }

    /*
    |--------------------------------------------------------------------------
    | Lawyer Validation
    |--------------------------------------------------------------------------
    */

    if ($error === '' && $role === 'lawyer') {

        if ($lawyer_specialty <= 0) {

            $error = 'Please select a specialty.';
        } else {

            $stmt = dbSelect(
                "SELECT id
                 FROM specialties
                 WHERE id = :id
                 AND active = 1
                 LIMIT 1",
                [
                    ':id' => $lawyer_specialty
                ]
            );

            if ($stmt->rowCount() === 0) {
                $error = 'Please select a valid specialty.';
            }
        }

        if (
            $error === '' &&
            !is_numeric($hourly_rate)
        ) {
            $error = 'Hourly rate must be numeric.';
        }

        if (
            $error === '' &&
            (
                (float)$hourly_rate < 1 ||
                (float)$hourly_rate > 1000
            )
        ) {
            $error = 'Hourly rate must be between $1 and $1000.';
        }
        if (
            $error === '' &&
            mb_strlen($bio) < 20
        ) {
            $error =
                'Please provide a professional bio of at least 20 characters.';
        }

        if (
            $error === '' &&
            mb_strlen($bio) > 2000
        ) {
            $error =
                'Please provide a professional bio that does not exceed 2000 characters.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Client Validation
    |--------------------------------------------------------------------------
    */

    if ($error === '' && $role === 'client') {

        if ($client_specialty <= 0) {

            $error = 'Please select a specialty.';
        } else {

            $stmt = dbSelect(
                "SELECT id
                 FROM specialties
                 WHERE id = :id
                 AND active = 1
                 LIMIT 1",
                [
                    ':id' => $client_specialty
                ]
            );

            if ($stmt->rowCount() === 0) {
                $error = 'Please select a valid specialty.';
            }
        }

        if (
            $error === '' &&
            mb_strlen($case_description) < 20
        ) {
            $error = 'Please provide more detail about your legal matter.';
        }

        if (
            $error === '' &&
            mb_strlen($case_description) > 5000
        ) {
            $error = 'Case description cannot exceed 5000 characters.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Email
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $stmt = dbSelect(
            "SELECT id
             FROM users
             WHERE email = :email
             LIMIT 1",
            [
                ':email' => $email
            ]
        );

        if ($stmt->rowCount() > 0) {
            $error = 'That email is already registered.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create User
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $hash = password_hash(
            $pass,
            PASSWORD_DEFAULT
        );

        dbExecute(
            "INSERT INTO users
            (
                role,
                name,
                email,
                password_hash
            )
            VALUES
            (
                :role,
                :name,
                :email,
                :hash
            )",
            [
                ':role'  => $role,
                ':name'  => $name,
                ':email' => $email,
                ':hash'  => $hash
            ]
        );

        global $pdo;

        $userId = (int)$pdo->lastInsertId();

        if ($role === 'lawyer') {

            $hourly_rate = round((float)$hourly_rate, 2);

            dbExecute(
                "INSERT INTO lawyers
                (
                    user_id,
                    specialty_id,
                    hourly_rate,
                    bio
                )
                VALUES
                (
                    :user_id,
                    :specialty_id,
                    :hourly_rate,
                    :bio
                )",
                [
                    ':user_id'      => $userId,
                    ':specialty_id' => $lawyer_specialty,
                    ':hourly_rate'  => $hourly_rate,
                    ':bio'          => $bio
                ]
            );
        }

        if ($role === 'client') {

            dbExecute(
                "INSERT INTO client_requests
                (
                    user_id,
                    specialty_id,
                    case_description
                )
                VALUES
                (
                    :user_id,
                    :specialty_id,
                    :case_description
                )",
                [
                    ':user_id'          => $userId,
                    ':specialty_id'     => $client_specialty,
                    ':case_description' => $case_description
                ]
            );
        }

        header('Location: login.php?created=1');
        exit;
    }
    
}
require_once 'includes/header.php';
?>

<h2 class="lb-title center">Sign Up</h2>

<div class="lb-form-container">

    <?php if ($error): ?>
        <div class="lb-error">
            <?= e($error) ?>
        </div>
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
            value="<?= e($name) ?>"
            required>

        <label for="email">Email</label>

        <input
            type="email"
            id="email"
            name="email"
            maxlength="255"
            value="<?= e($email) ?>"
            required>

        <label for="role">Account Type</label>

        <select
            id="role"
            name="role"
            onchange="toggleRoleFields()"
            required>

            <option value="">Select Account Type</option>

            <option
                value="client"
                <?= $role === 'client' ? 'selected' : '' ?>>
                Client
            </option>

            <option
                value="lawyer"
                <?= $role === 'lawyer' ? 'selected' : '' ?>>
                Lawyer
            </option>

        </select>

        <div id="lawyerFields" style="display:none">

            <label for="lawyer_specialty">Specialty</label>

            <select
                id="lawyer_specialty"
                name="lawyer_specialty">

                <option value="">Select Specialty</option>

                <?php foreach ($specialties as $s): ?>

                    <option
                        value="<?= (int)$s['id'] ?>"
                        <?= ((int)($_POST['lawyer_specialty'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>

                        <?= e($s['name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <label for="hourly_rate">Hourly Rate ($)</label>

            <input
                type="number"
                step="0.01"
                min="1"
                max="1000"
                id="hourly_rate"
                name="hourly_rate"
                value="<?= e($_POST['hourly_rate'] ?? '') ?>">

            <label for="bio">
                Professional Bio
            </label>

            <textarea
                id="bio"
                name="bio"
                rows="6"
                maxlength="2000"
                placeholder="Describe briefly your experience, areas of practice, certifications, etc."><?= e($_POST['bio'] ?? '') ?></textarea>

        </div>

        <div id="clientFields" style="display:none">

            <label for="client_specialty">Legal Matter Type</label>

            <select
                id="client_specialty"
                name="client_specialty">

                <option value="">Select Specialty</option>

                <?php foreach ($specialties as $s): ?>

                    <option
                        value="<?= (int)$s['id'] ?>"
                        <?= ((int)($_POST['client_specialty'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>

                        <?= e($s['name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <label for="case_description">
                Brief Description of Your Case
            </label>

            <textarea
                id="case_description"
                name="case_description"
                rows="6"
                maxlength="5000"
                placeholder="Briefly describe your case, any relevant details and the urgency of the matter"><?= e($_POST['case_description'] ?? '') ?></textarea>

        </div>

        <label for="pass">Password</label>

        <input
            type="password"
            id="pass"
            name="pass"
            maxlength="128"
            required>

        <button type="submit" class="lb-btn">
            Sign Up
        </button>

    </form>

</div>

<script>
    function toggleRoleFields() {
        const role = document.getElementById('role').value;

        const lawyerFields = document.getElementById('lawyerFields');
        const clientFields = document.getElementById('clientFields');

        const lawyerSpecialty =
            document.getElementById('lawyer_specialty');

        const hourlyRate =
            document.getElementById('hourly_rate');

        const bio =
            document.getElementById('bio');

        const clientSpecialty =
            document.getElementById('client_specialty');

        const caseDescription =
            document.getElementById('case_description');

        const isLawyer = role === 'lawyer';
        const isClient = role === 'client';

        lawyerFields.style.display = isLawyer ? 'block' : 'none';
        clientFields.style.display = isClient ? 'block' : 'none';

        lawyerSpecialty.required = isLawyer;
        hourlyRate.required = isLawyer;
        bio.required = isLawyer;

        clientSpecialty.required = isClient;
        caseDescription.required = isClient;

        lawyerSpecialty.disabled = !isLawyer;
        hourlyRate.disabled = !isLawyer;
        bio.disabled = !isLawyer;

        clientSpecialty.disabled = !isClient;
        caseDescription.disabled = !isClient;
    }

    document.addEventListener(
        'DOMContentLoaded',
        toggleRoleFields
    );
</script>

</main>
</body>

</html>