<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

/*
|--------------------------------------------------------------------------
| Administrator authentication
|--------------------------------------------------------------------------
*/

requireLogin();
requireAdmin();

$admin_user_id = (int)$_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Require POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

/*
|--------------------------------------------------------------------------
| CSRF validation
|--------------------------------------------------------------------------
*/

if (!csrfValidate($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Invalid request.');
}

/*
|--------------------------------------------------------------------------
| Validate entity type
|--------------------------------------------------------------------------
*/

$allowedTypes = [
    'user',
    'client',
    'lawyer',
    'specialty'
];

$type = $_POST['type'] ?? '';

if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    exit('Invalid entity type.');
}

/*
|--------------------------------------------------------------------------
| Validate entity ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    http_response_code(400);
    exit('Invalid record ID.');
}

$id = (int)$id;

/*
|--------------------------------------------------------------------------
| Verify database connection
|--------------------------------------------------------------------------
*/

global $pdo;

if (!$pdo instanceof PDO) {
    throw new RuntimeException(
        'Database connection unavailable.'
    );
}

/*
|--------------------------------------------------------------------------
| Delete record
|--------------------------------------------------------------------------
*/

try {
    $pdo->beginTransaction();

    switch ($type) {
        /*
        |--------------------------------------------------------------------------
        | Delete lawyer
        |--------------------------------------------------------------------------
        */

        case 'lawyer':
            if ($id === $admin_user_id) {
                throw new RuntimeException(
                    'You cannot delete your own account.'
                );
            }

            /*
             * Load only a user whose current role is lawyer.
             * Administrator accounts cannot pass this query.
             */
            $targetStmt = dbSelect(
                "SELECT
                    u.id,
                    u.role
                 FROM users u
                 INNER JOIN lawyers l
                    ON l.user_id = u.id
                 WHERE u.id = :id
                   AND u.role = 'lawyer'
                 LIMIT 1",
                [
                    ':id' => $id
                ]
            );

            $targetUser = $targetStmt->fetch();

            if (!$targetUser) {
                throw new RuntimeException(
                    'Lawyer not found or account is protected.'
                );
            }

            /*
             * Messages are deleted automatically because your messages
             * foreign keys use ON DELETE CASCADE.
             */

            dbExecute(
                "DELETE FROM lawyers
                 WHERE user_id = :user_id",
                [
                    ':user_id' => $id
                ]
            );

            /*
             * Repeat the non-administrator restriction in the DELETE.
             */
            dbExecute(
                "DELETE FROM users
                 WHERE id = :id
                   AND role = 'lawyer'",
                [
                    ':id' => $id
                ]
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | Delete client
        |--------------------------------------------------------------------------
        */

        case 'client':
            if ($id === $admin_user_id) {
                throw new RuntimeException(
                    'You cannot delete your own account.'
                );
            }

            /*
             * Load only a client. Administrator accounts cannot pass.
             */
            $targetStmt = dbSelect(
                "SELECT id, role
                 FROM users
                 WHERE id = :id
                   AND role = 'client'
                 LIMIT 1",
                [
                    ':id' => $id
                ]
            );

            $targetUser = $targetStmt->fetch();

            if (!$targetUser) {
                throw new RuntimeException(
                    'Client not found or account is protected.'
                );
            }

            /*
             * client_requests uses ON DELETE RESTRICT, so requests must
             * be deleted before deleting the user.
             */
            dbExecute(
                "DELETE FROM client_requests
                 WHERE user_id = :user_id",
                [
                    ':user_id' => $id
                ]
            );

            dbExecute(
                "DELETE FROM users
                 WHERE id = :id
                   AND role = 'client'",
                [
                    ':id' => $id
                ]
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | Delete generic non-administrator user
        |--------------------------------------------------------------------------
        */

        case 'user':
            if ($id === $admin_user_id) {
                throw new RuntimeException(
                    'You cannot delete your own account.'
                );
            }

            /*
             * Only client and lawyer records are returned.
             * An administrator ID will appear as not found/protected.
             */
            $targetStmt = dbSelect(
                "SELECT id, role
                 FROM users
                 WHERE id = :id
                   AND role IN ('client', 'lawyer')
                 LIMIT 1",
                [
                    ':id' => $id
                ]
            );

            $targetUser = $targetStmt->fetch();

            if (!$targetUser) {
                throw new RuntimeException(
                    'User not found or account is protected.'
                );
            }

            $targetRole = $targetUser['role'] ?? '';

            if (
                !in_array(
                    $targetRole,
                    ['client', 'lawyer'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Protected accounts cannot be deleted.'
                );
            }

            if ($targetRole === 'lawyer') {
                dbExecute(
                    "DELETE FROM lawyers
                     WHERE user_id = :user_id",
                    [
                        ':user_id' => $id
                    ]
                );
            }

            if ($targetRole === 'client') {
                dbExecute(
                    "DELETE FROM client_requests
                     WHERE user_id = :user_id",
                    [
                        ':user_id' => $id
                    ]
                );
            }

            /*
             * The SQL condition prevents administrator deletion even if
             * the earlier PHP checks are changed or bypassed.
             */
            dbExecute(
                "DELETE FROM users
                 WHERE id = :id
                   AND role IN ('client', 'lawyer')",
                [
                    ':id' => $id
                ]
            );

            break;

        /*
        |--------------------------------------------------------------------------
        | Delete specialty
        |--------------------------------------------------------------------------
        */

        case 'specialty':
            $specialtyStmt = dbSelect(
                "SELECT id
                 FROM specialties
                 WHERE id = :id
                 LIMIT 1",
                [
                    ':id' => $id
                ]
            );

            if (!$specialtyStmt->fetch()) {
                throw new RuntimeException(
                    'Specialty not found.'
                );
            }

            /*
             * Prevent deleting a specialty assigned to lawyers.
             */
            $assignedLawyersStmt = dbSelect(
                "SELECT COUNT(*) AS total
                 FROM lawyers
                 WHERE specialty_id = :specialty_id",
                [
                    ':specialty_id' => $id
                ]
            );

            $assignedLawyers =
                $assignedLawyersStmt->fetch();

            if (
                (int)($assignedLawyers['total'] ?? 0) > 0
            ) {
                throw new RuntimeException(
                    'Cannot delete a specialty assigned to lawyers.'
                );
            }

            /*
             * Prevent deleting a specialty used by client requests.
             */
            $assignedRequestsStmt = dbSelect(
                "SELECT COUNT(*) AS total
                 FROM client_requests
                 WHERE specialty_id = :specialty_id",
                [
                    ':specialty_id' => $id
                ]
            );

            $assignedRequests =
                $assignedRequestsStmt->fetch();

            if (
                (int)($assignedRequests['total'] ?? 0) > 0
            ) {
                throw new RuntimeException(
                    'Cannot delete a specialty assigned to client requests.'
                );
            }

            dbExecute(
                "DELETE FROM specialties
                 WHERE id = :id",
                [
                    ':id' => $id
                ]
            );

            break;
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    lb_log_error(
        'Administrative deletion failed. Type: '
            . $type
            . ', ID: '
            . $id
            . ', administrator ID: '
            . $admin_user_id
            . '. Error: '
            . $exception->getMessage()
    );

    /*
     * These controlled messages are safe to show because they do not
     * contain SQL or database details.
     */
    $safeMessages = [
        'You cannot delete your own account.',
        'Lawyer not found or account is protected.',
        'Client not found or account is protected.',
        'User not found or account is protected.',
        'Protected accounts cannot be deleted.',
        'Specialty not found.',
        'Cannot delete a specialty assigned to lawyers.',
        'Cannot delete a specialty assigned to client requests.'
    ];

    $publicMessage = in_array(
        $exception->getMessage(),
        $safeMessages,
        true
    )
        ? $exception->getMessage()
        : 'The record could not be deleted.';

    http_response_code(400);

    exit(e($publicMessage));
}

/*
|--------------------------------------------------------------------------
| Redirect after successful deletion
|--------------------------------------------------------------------------
*/

$redirectPage = match ($type) {
    'lawyer' =>
    'admin_lawyers.php',

    'client' =>
    'admin_clients.php',

    'specialty' =>
    'admin_specialties.php',

    default =>
    'admin_users.php'
};

header(
    'Location: '
        . $redirectPage
        . '?deleted=1'
);

exit;
