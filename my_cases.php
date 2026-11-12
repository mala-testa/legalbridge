<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Session integrity
|--------------------------------------------------------------------------
*/

if (!verify_session_fingerprint(true)) {
    lb_destroy_session();

    http_response_code(403);

    exit('Session integrity check failed. Please log in again.');
}

/*
|--------------------------------------------------------------------------
| Lawyer authorization
|--------------------------------------------------------------------------
*/

if (($_SESSION['role'] ?? '') !== 'lawyer') {
    http_response_code(403);
    exit('Access denied.');
}

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Load lawyer profile
|--------------------------------------------------------------------------
*/

$lawyerStmt = dbSelect(
    "SELECT specialty_id
     FROM lawyers
     WHERE user_id = :user_id
       AND active = 1
     LIMIT 1",
    [
        ':user_id' => $user_id
    ]
);

$lawyer = $lawyerStmt->fetch();

$profileError = '';
$cases = [];

if (!$lawyer) {
    $profileError =
        'Your active lawyer profile could not be found.';
} else {
    $specialty_id = (int)$lawyer['specialty_id'];

    /*
    |--------------------------------------------------------------------------
    | Load matching client requests
    |--------------------------------------------------------------------------
    */

    $cases = dbSelect(
        "SELECT
            r.id,
            r.case_description,
            r.status,
            r.created_at,
            u.name AS client_name,
            u.email AS client_email
         FROM client_requests r
         INNER JOIN users u
            ON r.user_id = u.id
         WHERE r.specialty_id = :specialty_id
           AND r.status IN ('open', 'matched')
           AND u.role = 'client'
           AND u.active = 1
         ORDER BY r.created_at DESC, r.id DESC",
        [
            ':specialty_id' => $specialty_id
        ]
    )->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Load HTML after security checks and database processing
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    My Cases
</h2>

<div class="lb-section">

    <?php if ($profileError !== ''): ?>

        <div class="lb-error">
            <?= e($profileError) ?>
        </div>

    <?php elseif (empty($cases)): ?>

        <div class="lb-card">
            <p>
                No active client requests currently match your specialty.
            </p>
        </div>

    <?php else: ?>

        <?php foreach ($cases as $case): ?>

            <?php
            $case_id = (int)$case['id'];

            $client_name =
                $case['client_name'] ?? 'Unknown';

            $client_email =
                $case['client_email'] ?? '';

            $case_description =
                $case['case_description'] ?? '';

            $status =
                $case['status'] ?? 'open';

            $created_timestamp =
                strtotime($case['created_at'] ?? '');

            $created_date =
                $created_timestamp !== false
                ? date('F j, Y', $created_timestamp)
                : '';
            ?>

            <div class="lb-card">

                <h3>
                    Case #<?= $case_id ?>
                </h3>

                <p>
                    <strong>Client:</strong>
                    <?= e($client_name) ?>

                    <?php if ($client_email !== ''): ?>
                        (<?= e($client_email) ?>)
                    <?php endif; ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?= e(ucfirst($status)) ?>
                </p>

                <?php if ($created_date !== ''): ?>

                    <p>
                        <strong>Submitted:</strong>
                        <?= e($created_date) ?>
                    </p>

                <?php endif; ?>

                <p>
                    <strong>Description:</strong><br>
                    <?= nl2br(e($case_description)) ?>
                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</main>
</body>

</html>