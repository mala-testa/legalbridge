<?php
// api/login.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
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

$email = filter_var(sanitizeString($input['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
$pass  = $input['pass'] ?? '';

if ($email === '' || $pass === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password required']);
    exit;
}

try {
    $stmt = dbSelect("SELECT id, role, email, password_hash, active FROM users WHERE email = :email LIMIT 1", [':email' => $email]);

    if ($stmt->rowCount() === 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    $row = $stmt->fetch();
    if ((int)$row['active'] !== 1 || !password_verify($pass, $row['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

// Option A: use session-based auth (same-origin AJAX)
lb_secure_session_start();
$_SESSION['user_id'] = (int)$row['id'];
$_SESSION['email']   = $row['email'];
$_SESSION['role']    = $row['role'];
lb_regenerate_session();

echo json_encode(['success' => true, 'user' => ['id' => (int)$row['id'], 'email' => $row['email'], 'role' => $row['role']]]);
exit;
} catch (Exception $e) {
    error_log('api/login.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
}