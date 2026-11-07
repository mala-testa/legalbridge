<?php
// api/register_client.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Accept form-encoded or JSON body
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) $input = $data;
}

$name     = sanitizeString($input['name'] ?? '');
$emailRaw = $input['email'] ?? '';
$email    = filter_var(sanitizeString($emailRaw), FILTER_VALIDATE_EMAIL) ?: '';
$password = $input['password'] ?? '';

// Optional initial request fields
$specialty_id     = isset($input['specialty_id']) ? (int)$input['specialty_id'] : null;
$case_description = isset($input['case_description']) ? sanitizeString($input['case_description']) : null;

if ($name === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Name, valid email, and password are required']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

// Check for existing email
$stmt = dbSelect("SELECT id FROM users WHERE email = :email", [':email' => $email]);
if ($stmt->rowCount() > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already registered']);
    exit;
}

try {
    global $pdo;
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection not available');
    }

    $pdo->beginTransaction();

    // Insert user
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insertUserSql = "INSERT INTO users (role, name, email, password_hash, active) VALUES (:role, :name, :email, :hash, 1)";
    $ok = dbExecute($insertUserSql, [
        ':role' => 'client',
        ':name' => $name,
        ':email' => $email,
        ':hash' => $hash
    ]);

    if (!$ok) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create user']);
        exit;
    }

    $userId = (int)$pdo->lastInsertId();

    // If client provided an initial request, insert it
    if (!empty($specialty_id) && !empty($case_description)) {
        $insertRequestSql = "INSERT INTO client_requests (user_id, specialty_id, case_description, status) VALUES (:user_id, :specialty_id, :case_description, 'open')";
        $ok2 = dbExecute($insertRequestSql, [
            ':user_id' => $userId,
            ':specialty_id' => (int)$specialty_id,
            ':case_description' => $case_description
        ]);

        if (!$ok2) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create initial request']);
            exit;
        }
    }

    $pdo->commit();

    // Optionally log the user in (session-based)
    lb_secure_session_start();
    $_SESSION['user_id'] = $userId;
    $_SESSION['email']   = $email;
    $_SESSION['role']    = 'client';
    lb_regenerate_session();

    echo json_encode(['success' => true, 'user_id' => $userId]);
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Register client error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error while creating account']);
    exit;
}
