<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

/*
|--------------------------------------------------------------------------
| Require authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify session integrity
|--------------------------------------------------------------------------
*/

if (!verify_session_fingerprint(true)) {
    lb_destroy_session();

    http_response_code(403);

    exit('Session integrity check failed. Please log in again.');
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| Redirect administrators before HTML output
|--------------------------------------------------------------------------
*/

if ($role === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate the account role
|--------------------------------------------------------------------------
*/

if (!in_array($role, ['client', 'lawyer'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| Load lawyer profile details
|--------------------------------------------------------------------------
*/

$lawyerProfileError = '';
$specialtyName      = '';

if ($role === 'lawyer') {
    $lawyerStmt = dbSelect(
        "SELECT
            l.specialty_id,
            s.name AS specialty_name
         FROM lawyers l
         INNER JOIN specialties s
            ON l.specialty_id = s.id
         WHERE l.user_id = :user_id
           AND l.active = 1
           AND s.active = 1
         LIMIT 1",
        [
            ':user_id' => $user_id
        ]
    );

    $lawyerProfile = $lawyerStmt->fetch();

    if (!$lawyerProfile) {
        $lawyerProfileError =
            'Your active lawyer profile or specialty could not be found.';
    } else {
        $specialtyName =
            $lawyerProfile['specialty_name'] ?? 'Unknown';
    }
}

/*
|--------------------------------------------------------------------------
| Load HTML only after security checks and redirects
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    Dashboard
</h2>

<?php if ($role === 'client'): ?>

    <div class="lb-section center">

        <a
            class="lb-btn"
            href="create_request.php">
            Create New Request
        </a>

        <a
            class="lb-btn"
            href="my_requests.php">
            My Requests
        </a>

        <a
            class="lb-btn"
            href="matches.php">
            Matches
        </a>

    </div>

<?php elseif ($role === 'lawyer'): ?>

    <div class="lb-section center">

        <?php if ($lawyerProfileError !== ''): ?>

            <div class="lb-error">
                <?= e($lawyerProfileError) ?>
            </div>

            <p>
                <a
                    class="lb-btn"
                    href="profile.php">
                    Edit Profile
                </a>
            </p>

        <?php else: ?>

            <p>
                <strong>Specialty:</strong>
                <?= e($specialtyName) ?>
            </p>

            <a
                class="lb-btn"
                href="my_cases.php">
                My Cases
            </a>

            <a
                class="lb-btn"
                href="matches.php">
                Matches
            </a>

            <a
                class="lb-btn"
                href="profile.php">
                Edit Profile
            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</main>
</body>

</html>