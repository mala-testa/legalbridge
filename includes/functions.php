<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/error_handler.php';

loadEnv(__DIR__ . '/../.env');

// ------------------------------------------------------------
// DATABASE CONNECTION
// ------------------------------------------------------------

$host = getenv('DB_HOST');
$data = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $host,
    $data,
    $charset
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {

    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        $options
    );

} catch (PDOException $e) {

    lb_log_error(
        'DB Connection Failed: ' .
        $e->getMessage()
    );

    die('<h3>Database connection error.</h3>');
}

// ------------------------------------------------------------
// SAFE QUERY HELPERS (Prepared Statements)
// ------------------------------------------------------------

function dbSelect($sql, $params = [])
{
    global $pdo;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

function dbExecute($sql, $params = [])
{
    global $pdo;

    $stmt = $pdo->prepare($sql);

    return $stmt->execute($params);
}

function createTable($name, $schema)
{
    global $pdo;

    try {
        $sql = "CREATE TABLE IF NOT EXISTS $name ($schema)";
        $pdo->exec($sql);
        echo "<p>Table <strong>$name</strong> created.</p>";
        error_log("Table $name created.");
    } catch (PDOException $e) {
        echo "<p style='color:red;'>Error creating table $name</p>";
        error_log("Table creation error ($name): " . $e->getMessage());
    }
}


// ------------------------------------------------------------
// INPUT SANITIZATION
// ------------------------------------------------------------
function sanitizeString($var): string
{
    if (!isset($var))
        return '';
    $var = trim($var);
    $var = strip_tags($var);
    return htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------------
// SAFE OUTPUT
// ------------------------------------------------------------
function e($string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// ------------------------------------------------------------
// PASSWORD SECURITY
// ------------------------------------------------------------
function verifyPassword($password, $hash): bool
{
    return password_verify($password, $hash);
}

function needsRehash($hash): bool
{
    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}

// ------------------------------------------------------------
// CSRF PROTECTION
// ------------------------------------------------------------
function csrfToken(): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfValidate(?string $token): bool
{
    return session_status() === PHP_SESSION_ACTIVE
        && isset($_SESSION['csrf'])
        && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}


// -------------------------
// admin auth 
// -------------------------

function requireAdmin(): void
{
    if (
        !isset($_SESSION['user_id']) ||
        ($_SESSION['role'] ?? '') !== 'admin'
    ) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    if (!verify_session_fingerprint(true)) {

        lb_destroy_session();

        http_response_code(403);

        exit(
            'Session integrity check failed. Please log in again.'
        );
    }
}