<?php
// api/register_lawyer.php
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

$name         = sanitizeString($input['name'] ?? '');
$emailRaw     = $input['email'] ?? '';
$email        = filter_var(sanitizeString($emailRaw), FILTER_VALIDATE_EMAIL) ?: '';
$password     = $input['password'] ?? '';
$specialty_id = isset($input['specialty_id']) ? (int)$input['specialty_id'] : 0;
$hourly_rate  = isset($input['hourly_rate']) ? (float)$input['hourly_rate'] : 0.00;
$bio          = sanitizeString($input['bio'] ?? '');

if ($name === '' || $email === '' || $password === '' || $specialty_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Name, valid email, password, and specialty_id are required']);
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
    // Use the global PDO instance from includes/functions.php
    global $pdo;
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection not available');
    }

    $pdo->beginTransaction();

    // Insert user
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insertUserSql = "INSERT INTO users (role, name, email, password_hash, active) VALUES (:role, :name, :email, :hash, 1)";
    $ok = dbExecute($insertUserSql, [
        ':role' => 'lawyer',
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

    // Get the inserted user id from the PDO instance
    $userId = (int)$pdo->lastInsertId();

    // Insert lawyer profile
    $insertLawyerSql = "INSERT INTO lawyers (user_id, specialty_id, hourly_rate, bio, active) VALUES (:user_id, :specialty_id, :hourly_rate, :bio, 1)";
    $ok2 = dbExecute($insertLawyerSql, [
        ':user_id' => $userId,
        ':specialty_id' => $specialty_id,
        ':hourly_rate' => number_format($hourly_rate, 2, '.', ''),
        ':bio' => $bio
    ]);

    if (!$ok2) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create lawyer profile']);
        exit;
    }

    $pdo->commit();

    // Optionally log the user in (session-based)
    lb_secure_session_start();
    $_SESSION['user_id'] = $userId;
    $_SESSION['email']   = $email;
    $_SESSION['role']    = 'lawyer';
    lb_regenerate_session();

    echo json_encode(['success' => true, 'user_id' => $userId]);
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Register lawyer error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error while creating account']);
    exit;
}
