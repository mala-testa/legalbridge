<?php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}


$type = $_POST['type'] ?? '';
$id   = (int)($_POST['id'] ?? 0);

$allowedTypes = [
    'user',
    'client',
    'lawyer',
    'specialty'
];

if (!in_array($type, $allowedTypes, true)) {
    exit('Invalid type.');
}

if ($id <= 0) {
    exit('Invalid ID.');
}

global $pdo;

if (!$pdo instanceof PDO) {
    throw new RuntimeException(
        'Database connection unavailable.'
    );
}

try {

    $pdo->beginTransaction();

    switch ($type) {

        case 'lawyer':

            if ($id === (int)$_SESSION['user_id']) {
                throw new RuntimeException(
                    'You cannot delete your own account.'
                );
            }

            $exists = dbSelect(
                'SELECT user_id
                FROM lawyers
                WHERE user_id = ?',
                [$id]
            )->fetch();

            if(!$exists) {
                throw new RuntimeException('Lawyer not found');
            }

            dbExecute(
                'DELETE FROM lawyers
                 WHERE user_id = ?',
                [$id]
            );

            dbExecute(
                'DELETE FROM users
                WHERE id = ?',
                [$id]
            );

            break;

        case 'client':

            if ($id === (int)$_SESSION['user_id']) {
                throw new RuntimeException(
                    'You cannot delete your own account.'
                );
            }

            $exists = dbSelect(
                'SELECT id
                FROM users
                WHERE id = ?
                AND role = ?',
                [$id, 'client']
            )->fetch();

            if(!$exists) {
                throw new RuntimeException('Client not found');
            }

            dbExecute(
                'DELETE FROM client_requests
                 WHERE user_id = ?',
                [$id]
            );

            dbExecute(
                'DELETE FROM users
                 WHERE id = ?',
                [$id]
            );

            break;

        case 'user':

            if ($id === (int)$_SESSION['user_id']) {
                throw new RuntimeException(
                    'You cannot delete your own account.'
                );
            }

            $user = dbSelect(
                'SELECT role
                FROM users
                WHERE id = ?',
                [$id]
            )->fetch();

            if(!$user) {
                throw new RuntimeException('User not found');
            }

            if  ($user['role'] === 'lawyer') {
                dbExecute(
                    'DELETE FROM lawyers
                    WHERE user_id = ?',
                    [$id]
                );
            }

            dbExecute(
                'DELETE FROM client_requests
                 WHERE user_id = ?',
                [$id]
            );

            dbExecute(
                'DELETE FROM users
                 WHERE id = ?',
                [$id]
            );

            break;

        case 'specialty':

            $assigned = dbSelect(
                'SELECT COUNT(*) AS total
                 FROM lawyers
                 WHERE specialty_id = ?',
                [$id]
            )->fetch();

            if ((int)$assigned['total'] > 0) {
                throw new RuntimeException(
                    'Cannot delete a specialty that is assigned to lawyers.'
                );
            }

            $requests = dbSelect(
                'SELECT COUNT(*) AS total
                FROM client_requests
                WHERE specialty_id = ?',
                [$id]
            )->fetch();

            if ((int)$requests['total'] > 0) {
                throw new RuntimeException('Cannot delete a specialty assigned to client requests.');
            }

            dbExecute(
                'DELETE FROM specialties
                 WHERE id = ?',
                [$id]
            );

            break;
    }

    $pdo->commit();
} catch (Throwable $e) {

    if (
        isset($pdo) &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    error_log(
        'DELETE ERROR: ' .
        $e->getMessage()
    );

    http_response_code(400);

    exit(htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        ));
}

header(
    'Location: ' .
        match ($type) {
            'lawyer'    => 'admin_lawyers.php',
            'client'    => 'admin_clients.php',
            'specialty' => 'admin_specialties.php',
            default     => 'admin_users.php'
        }
);

exit;
