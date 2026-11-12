<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

/*
|--------------------------------------------------------------------------
| Authentication and session validation
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
$role    = $_SESSION['role'] ?? '';

if (!in_array($role, ['lawyer', 'client'], true)) {
    http_response_code(403);
    exit('This profile page is only available to clients and lawyers.');
}

$error   = '';
$success = '';

$name  = '';
$email = '';

$lawyer_specialty = 0;
$hourly_rate       = '';
$bio               = '';

$client_request_id = 0;
$client_specialty  = 0;
$case_description  = '';

/*
|--------------------------------------------------------------------------
| Load active specialties
|--------------------------------------------------------------------------
*/

$specialties = dbSelect(
    "SELECT id, name
     FROM specialties
     WHERE active = 1
     ORDER BY name"
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Load user information
|--------------------------------------------------------------------------
*/

$userStmt = dbSelect(
    "SELECT name, email
     FROM users
     WHERE id = :id
       AND active = 1
     LIMIT 1",
    [
        ':id' => $user_id
    ]
);

$userRow = $userStmt->fetch();

if (!$userRow) {
    lb_destroy_session();

    http_response_code(404);

    exit('User account was not found.');
}

$name  = $userRow['name'];
$email = $userRow['email'];

/*
|--------------------------------------------------------------------------
| Load lawyer information
|--------------------------------------------------------------------------
*/

if ($role === 'lawyer') {
    $lawStmt = dbSelect(
        "SELECT specialty_id, hourly_rate, bio
         FROM lawyers
         WHERE user_id = :uid
         LIMIT 1",
        [
            ':uid' => $user_id
        ]
    );

    $lawRow = $lawStmt->fetch();

    if ($lawRow) {
        $lawyer_specialty = (int)$lawRow['specialty_id'];
        $hourly_rate      = $lawRow['hourly_rate'];
        $bio              = $lawRow['bio'] ?? '';
    }
}

/*
|--------------------------------------------------------------------------
| Load client's most recent request
|--------------------------------------------------------------------------
|
| Your schema allows a client to have multiple requests. This profile page
| edits the client's most recently created request.
|
*/

if ($role === 'client') {
    $requestStmt = dbSelect(
        "SELECT id, specialty_id, case_description
         FROM client_requests
         WHERE user_id = :uid
         ORDER BY created_at DESC, id DESC
         LIMIT 1",
        [
            ':uid' => $user_id
        ]
    );

    $requestRow = $requestStmt->fetch();

    if ($requestRow) {
        $client_request_id = (int)$requestRow['id'];
        $client_specialty  = (int)$requestRow['specialty_id'];
        $case_description  = $requestRow['case_description'];
    }
}

/*
|--------------------------------------------------------------------------
| Process profile update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($role === 'lawyer') {
        $lawyer_specialty =
            (int)($_POST['lawyer_specialty'] ?? 0);

        $hourly_rate =
            trim($_POST['hourly_rate'] ?? '');

        $bio = trim($_POST['bio'] ?? '');
        $bio = preg_replace('/\s+/u', ' ', $bio);
    }

    if ($role === 'client') {
        $client_request_id =
            (int)($_POST['client_request_id'] ?? 0);

        $client_specialty =
            (int)($_POST['client_specialty'] ?? 0);

        $case_description =
            trim($_POST['case_description'] ?? '');

        $case_description =
            preg_replace('/\s+/u', ' ', $case_description);
    }

    /*
    |--------------------------------------------------------------------------
    | CSRF validation
    |--------------------------------------------------------------------------
    */

    if (!csrfValidate($_POST['csrf'] ?? '')) {
        $error = 'Invalid request. Please refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Name validation
    |--------------------------------------------------------------------------
    */

    if ($error === '' && $name === '') {
        $error = 'Name is required.';
    }

    if (
        $error === '' &&
        mb_strlen($name) < 2
    ) {
        $error = 'Name must contain at least 2 characters.';
    }

    if (
        $error === '' &&
        mb_strlen($name) > 100
    ) {
        $error = 'Name cannot exceed 100 characters.';
    }

    if (
        $error === '' &&
        !preg_match("/^[\p{L}\s'.\-]+$/u", $name)
    ) {
        $error = 'Please enter a valid name.';
    }

    /*
    |--------------------------------------------------------------------------
    | Lawyer validation
    |--------------------------------------------------------------------------
    */

    if ($error === '' && $role === 'lawyer') {
        if ($lawyer_specialty <= 0) {
            $error = 'Please select a specialty.';
        }

        if ($error === '') {
            $specialtyStmt = dbSelect(
                "SELECT id
                 FROM specialties
                 WHERE id = :id
                   AND active = 1
                 LIMIT 1",
                [
                    ':id' => $lawyer_specialty
                ]
            );

            if (!$specialtyStmt->fetch()) {
                $error = 'Please select a valid active specialty.';
            }
        }

        if (
            $error === '' &&
            (
                $hourly_rate === '' ||
                !is_numeric($hourly_rate)
            )
        ) {
            $error = 'Hourly rate must be a valid number.';
        }

        if (
            $error === '' &&
            (
                (float)$hourly_rate < 1 ||
                (float)$hourly_rate > 1000
            )
        ) {
            $error = 'Hourly rate must be between $1 and $1,000.';
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
            $error = 'Professional bio cannot exceed 2,000 characters.';
        }

        if ($error === '') {
            $lawyerStmt = dbSelect(
                "SELECT user_id
                 FROM lawyers
                 WHERE user_id = :uid
                 LIMIT 1",
                [
                    ':uid' => $user_id
                ]
            );

            if (!$lawyerStmt->fetch()) {
                $error = 'Your lawyer profile could not be found.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Client validation
    |--------------------------------------------------------------------------
    */

    if ($error === '' && $role === 'client') {
        if ($client_request_id <= 0) {
            $error = 'Your client request could not be found.';
        }

        if (
            $error === '' &&
            $client_specialty <= 0
        ) {
            $error = 'Please select a legal matter type.';
        }

        if ($error === '') {
            $specialtyStmt = dbSelect(
                "SELECT id
                 FROM specialties
                 WHERE id = :id
                   AND active = 1
                 LIMIT 1",
                [
                    ':id' => $client_specialty
                ]
            );

            if (!$specialtyStmt->fetch()) {
                $error = 'Please select a valid active specialty.';
            }
        }

        if (
            $error === '' &&
            mb_strlen($case_description) < 20
        ) {
            $error =
                'Please provide at least 20 characters describing your case.';
        }

        if (
            $error === '' &&
            mb_strlen($case_description) > 5000
        ) {
            $error = 'Case description cannot exceed 5,000 characters.';
        }

        /*
         * Verify that the submitted request belongs to the signed-in client.
         */
        if ($error === '') {
            $requestOwnerStmt = dbSelect(
                "SELECT id
                 FROM client_requests
                 WHERE id = :request_id
                   AND user_id = :user_id
                 LIMIT 1",
                [
                    ':request_id' => $client_request_id,
                    ':user_id'    => $user_id
                ]
            );

            if (!$requestOwnerStmt->fetch()) {
                $error =
                    'The selected client request does not belong to your account.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update profile
    |--------------------------------------------------------------------------
    */

    if ($error === '') {
        try {
            global $pdo;

            $pdo->beginTransaction();

            dbExecute(
                "UPDATE users
                 SET name = :name
                 WHERE id = :id",
                [
                    ':name' => $name,
                    ':id'   => $user_id
                ]
            );

            if ($role === 'lawyer') {
                $hourly_rate =
                    round((float)$hourly_rate, 2);

                dbExecute(
                    "UPDATE lawyers
                     SET specialty_id = :specialty_id,
                         hourly_rate = :hourly_rate,
                         bio = :bio
                     WHERE user_id = :user_id",
                    [
                        ':specialty_id' => $lawyer_specialty,
                        ':hourly_rate'  => $hourly_rate,
                        ':bio'          => $bio,
                        ':user_id'      => $user_id
                    ]
                );
            }

            if ($role === 'client') {
                dbExecute(
                    "UPDATE client_requests
                     SET specialty_id = :specialty_id,
                         case_description = :case_description
                     WHERE id = :request_id
                       AND user_id = :user_id",
                    [
                        ':specialty_id'    => $client_specialty,
                        ':case_description' => $case_description,
                        ':request_id'       => $client_request_id,
                        ':user_id'          => $user_id
                    ]
                );
            }

            $pdo->commit();

            $_SESSION['name'] = $name;

            $success = 'Your profile has been updated successfully.';
        } catch (Throwable $exception) {
            if (
                isset($pdo) &&
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            lb_log_error(
                'Profile update failed for user ID '
                    . $user_id
                    . ': '
                    . $exception->getMessage()
            );

            $error =
                'Your profile could not be updated. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load page header after POST processing
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">Your Profile</h2>

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
    <?php endif; ?>

    <form
        method="post"
        action="profile.php"
        class="lb-form">

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrfToken()) ?>">

        <label for="name">Name</label>

        <input
            type="text"
            id="name"
            name="name"
            minlength="2"
            maxlength="100"
            value="<?= e($name) ?>"
            required>

        <label for="email">Email</label>

        <input
            type="email"
            id="email"
            value="<?= e($email) ?>"
            disabled>

        <small>
            Your email address cannot be changed from this page.
        </small>

        <?php if ($role === 'lawyer'): ?>

            <label for="lawyer_specialty">
                Specialty
            </label>

            <select
                id="lawyer_specialty"
                name="lawyer_specialty"
                required>

                <option value="">
                    Select Specialty
                </option>

                <?php foreach ($specialties as $specialty): ?>

                    <option
                        value="<?= (int)$specialty['id'] ?>"
                        <?= (
                            $lawyer_specialty ===
                            (int)$specialty['id']
                        ) ? 'selected' : '' ?>>

                        <?= e($specialty['name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <label for="hourly_rate">
                Hourly Rate ($)
            </label>

            <input
                type="number"
                id="hourly_rate"
                name="hourly_rate"
                min="1"
                max="1000"
                step="0.01"
                value="<?= e((string)$hourly_rate) ?>"
                required>

            <label for="bio">
                Professional Bio
            </label>

            <textarea
                id="bio"
                name="bio"
                rows="6"
                minlength="20"
                maxlength="2000"
                placeholder="Describe your experience, areas of practice, education, and certifications."
                required><?= e($bio) ?></textarea>

        <?php elseif ($role === 'client'): ?>

            <input
                type="hidden"
                name="client_request_id"
                value="<?= $client_request_id ?>">

            <?php if ($client_request_id > 0): ?>

                <label for="client_specialty">
                    Legal Matter Type
                </label>

                <select
                    id="client_specialty"
                    name="client_specialty"
                    required>

                    <option value="">
                        Select Specialty
                    </option>

                    <?php foreach ($specialties as $specialty): ?>

                        <option
                            value="<?= (int)$specialty['id'] ?>"
                            <?= (
                                $client_specialty ===
                                (int)$specialty['id']
                            ) ? 'selected' : '' ?>>

                            <?= e($specialty['name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <label for="case_description">
                    Brief Description of Your Case
                </label>

                <textarea
                    id="case_description"
                    name="case_description"
                    rows="7"
                    minlength="20"
                    maxlength="5000"
                    placeholder="Describe your case, relevant details, and the urgency of the matter."
                    required><?= e($case_description) ?></textarea>

            <?php else: ?>

                <div class="lb-error">
                    No client request was found for your account.
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <?php if (
            $role === 'lawyer' ||
            $client_request_id > 0
        ): ?>

            <button
                type="submit"
                class="lb-btn">
                Save Profile
            </button>

        <?php endif; ?>

    </form>

    <form
        method="post"
        action="request_reset.php"
        class="lb-form"
        style="margin-top: 1rem;">

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrfToken()) ?>">

        <input
            type="hidden"
            name="email"
            value="<?= e($email) ?>">

        <label for="confirm_password">
            Enter your current password to request a password reset
        </label>

        <input
            type="password"
            id="confirm_password"
            name="password"
            maxlength="128"
            autocomplete="current-password"
            required>

        <button
            type="submit"
            class="lb-btn lb-btn-secondary">
            Request Password Reset
        </button>

    </form>

</div>

</main>
</body>

</html>