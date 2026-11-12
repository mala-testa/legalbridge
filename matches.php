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

$role = $_SESSION['role'] ?? '';

/*
|--------------------------------------------------------------------------
| Role authorization
|--------------------------------------------------------------------------
*/

if ($role === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

if (!in_array($role, ['client', 'lawyer'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Load HTML header after redirects and security checks
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    Matches
</h2>

<div class="lb-section">

    <?php

    /*
|--------------------------------------------------------------------------
| Client view
|--------------------------------------------------------------------------
*/

    if ($role === 'client') {
        $requests = dbSelect(
            "SELECT
            r.id,
            r.specialty_id,
            r.case_description,
            s.name AS specialty_name
         FROM client_requests r
         INNER JOIN specialties s
            ON r.specialty_id = s.id
         WHERE r.user_id = :user_id
         ORDER BY r.id DESC",
            [
                ':user_id' => $user_id
            ]
        )->fetchAll();

        if (empty($requests)) {
            echo "
            <div class='lb-card'>
                <p>You have not submitted any legal requests.</p>
            </div>
        ";
        } else {
            foreach ($requests as $request) {
                $request_id  = (int)$request['id'];
                $specialty_id = (int)$request['specialty_id'];

                $specialty_name =
                    $request['specialty_name'] ?? 'Unknown';

                $case_description =
                    $request['case_description'] ?? '';

                echo "<div class='lb-card'>";

                echo "<h3>Request #{$request_id}</h3>";

                echo "<p><strong>Specialty:</strong> "
                    . e($specialty_name)
                    . "</p>";

                echo "<p><strong>Description:</strong> "
                    . nl2br(e($case_description))
                    . "</p>";

                /*
            |--------------------------------------------------------------------------
            | Find active lawyers with the same specialty
            |--------------------------------------------------------------------------
            */

                $lawyers = dbSelect(
                    "SELECT
                    u.id,
                    u.name,
                    u.email,
                    l.hourly_rate,
                    l.bio
                 FROM lawyers l
                 INNER JOIN users u
                    ON l.user_id = u.id
                 WHERE l.specialty_id = :specialty_id
                   AND u.role = 'lawyer'
                   AND u.active = 1
                   AND l.active = 1
                 ORDER BY u.name",
                    [
                        ':specialty_id' => $specialty_id
                    ]
                )->fetchAll();

                if (empty($lawyers)) {
                    echo "
                    <p>
                        No lawyers currently match this specialty.
                    </p>
                ";
                } else {
                    echo "<ul class='lb-list'>";

                    foreach ($lawyers as $lawyer) {
                        $lawyer_name =
                            $lawyer['name'] ?? 'Unknown';

                        $lawyer_email =
                            $lawyer['email'] ?? '';

                        $hourly_rate =
                            number_format(
                                (float)($lawyer['hourly_rate'] ?? 0),
                                2
                            );

                        $bio =
                            $lawyer['bio'] ?? '';

                        echo "
                        <li class='lb-list-item'>
                            <strong>"
                            . e($lawyer_name)
                            . "</strong><br>

                            <strong>Email:</strong> "
                            . e($lawyer_email)
                            . "<br>

                            <strong>Rate:</strong> $"
                            . e($hourly_rate)
                            . "/hr<br>

                            <strong>Bio:</strong> "
                            . nl2br(e($bio))
                            . "
                        </li>
                    ";
                    }

                    echo "</ul>";
                }

                echo "</div>";
            }
        }
    }

    /*
|--------------------------------------------------------------------------
| Lawyer view
|--------------------------------------------------------------------------
*/ elseif ($role === 'lawyer') {
        /*
    |--------------------------------------------------------------------------
    | Load the lawyer's active profile and specialty
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

        $lawyerProfile = $lawyerStmt->fetch();

        if (!$lawyerProfile) {
            echo "
            <div class='lb-error'>
                <p>Your active lawyer profile could not be found.</p>
            </div>
        ";
        } else {
            $specialty_id =
                (int)$lawyerProfile['specialty_id'];

            /*
        |--------------------------------------------------------------------------
        | Find active clients' matching requests
        |--------------------------------------------------------------------------
        */

            $requests = dbSelect(
                "SELECT
                r.id,
                r.case_description,
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

            if (empty($requests)) {
                echo "
                <div class='lb-card'>
                    <p>
                        No active client requests currently match your
                        specialty.
                    </p>
                </div>
            ";
            } else {
                echo "<ul class='lb-list'>";

                foreach ($requests as $request) {
                    $request_id =
                        (int)$request['id'];

                    $case_description =
                        $request['case_description'] ?? '';

                    $client_name =
                        $request['client_name'] ?? 'Unknown';

                    $client_email =
                        $request['client_email'] ?? '';

                    echo "
                    <li class='lb-list-item'>
                        <strong>Request #{$request_id}</strong><br>

                        <strong>Client:</strong> "
                        . e($client_name)
                        . " ("
                        . e($client_email)
                        . ")<br>

                        <strong>Description:</strong> "
                        . nl2br(e($case_description))
                        . "
                    </li>
                ";
                }

                echo "</ul>";
            }
        }
    }

    ?>

</div>

</main>
</body>

</html>