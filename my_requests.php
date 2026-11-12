<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

/*
|--------------------------------------------------------------------------
| Require authenticated client
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify session integrity before any HTML output
|--------------------------------------------------------------------------
*/

if (!verify_session_fingerprint(true)) {
    lb_destroy_session();

    http_response_code(403);

    exit('Session integrity check failed. Please log in again.');
}

/*
|--------------------------------------------------------------------------
| Require client role
|--------------------------------------------------------------------------
*/

if (($_SESSION['role'] ?? '') !== 'client') {
    http_response_code(403);

    require_once __DIR__ . '/includes/header.php';

    echo "
        <div class='lb-error'>
            <h3>Access denied.</h3>
        </div>
    ";

    echo '</main></body></html>';
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Load requests belonging to the authenticated client
|--------------------------------------------------------------------------
*/

$requests = dbSelect(
    "SELECT
        r.id,
        r.case_description,
        s.name AS specialty_name
     FROM client_requests r
     LEFT JOIN specialties s
       ON r.specialty_id = s.id
     WHERE r.user_id = :user_id
     ORDER BY r.id DESC",
    [
        ':user_id' => $user_id
    ]
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Load HTML after authentication and database processing
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    My Requests
</h2>

<div class="lb-section">

    <?php if (empty($requests)): ?>

        <div class="lb-card">
            <p>You have not submitted any legal requests.</p>
        </div>

    <?php else: ?>

        <?php foreach ($requests as $request): ?>

            <?php
            $request_id = (int)$request['id'];

            $specialty_name =
                $request['specialty_name']
                ?? 'Unknown';

            $case_description =
                $request['case_description']
                ?? '';
            ?>

            <div class="lb-card">

                <h3>
                    Request #<?= $request_id ?>
                </h3>

                <p>
                    <strong>Specialty:</strong>
                    <?= e($specialty_name) ?>
                </p>

                <p>
                    <strong>Description:</strong>
                    <?= nl2br(e($case_description)) ?>
                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</main>
</body>

</html>