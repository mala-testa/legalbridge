<?php
require_once __DIR__ . '/functions.php';

// Centralized session and auth helpers
if (session_status() === PHP_SESSION_ACTIVE && !defined('LB_SESSION_TRACE_DONE')) {
    define('LB_SESSION_TRACE_DONE', true);
    $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    error_log("Session already active when auth.php loaded. Backtrace:");
    foreach ($bt as $i => $frame) {
        $file = $frame['file'] ?? '[internal]';
        $line = $frame['line'] ?? '';
        $func = $frame['function'] ?? '';
        error_log("#$i $file:$line $func");
    }
}

// Start a secure session if none exists
function lb_secure_session_start(): void
{
    // If a session is already active but not using our session name, destroy it first.
    $desiredName = lb_session_name();

    if (session_status() === PHP_SESSION_ACTIVE) {
        // If the active session already uses our name, nothing to do.
        if (session_name() === $desiredName) {
            return;
        }

        // Defensive: log that we found a pre-existing session with a different name.
        error_log("lb_secure_session_start: existing session active with name=" . session_name() . ". Replacing with " . $desiredName);

        // Clear server-side session data and destroy the session.
        $_SESSION = [];
        // Remove session cookie if present
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                $samesite = $params['samesite'] ?? 'Lax';
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $samesite
                ]);
            } else {
                setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
            }
        }

        // Destroy the current session
        @session_destroy();
        // Ensure PHP internal state is reset
        if (function_exists('session_reset')) {
            @session_reset();
        }
    }

    // Now set our session name and cookie params, then start a fresh session.
    session_name($desiredName);
    $params = lb_session_cookie_params();

    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    } else {
        session_set_cookie_params(
            $params['lifetime'],
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Finally start the session if not already active
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    /*
    |--------------------------------------------------------------------------
    | CSRF Token
    |--------------------------------------------------------------------------
    */

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}


// Session name and cookie defaults
function lb_session_name(): string
{
    return 'LBSESSID';
}

function lb_session_cookie_params(): array
{
    return [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',      // host-only; set to '.example.com' to share subdomains
        'secure' => false,   // set true when using HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

// Regenerate session id safely after privilege change
function lb_regenerate_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        lb_secure_session_start();
    }
    session_regenerate_id(true);
}

//Verify fingerprint
function verify_session_fingerprint(bool $require_recent = false): bool
{
    if (empty($_SESSION['fingerprint'])) return false;

    $current = hash(
        'sha256',
        ($_SERVER['REMOTE_ADDR'] ?? '') .
            ($_SERVER['HTTP_USER_AGENT'] ?? '') .
            session_id()
    );

    if (!hash_equals($_SESSION['fingerprint'], $current)) {
        return false;
    }

    if ($require_recent) {
        $max_age = 900; // 15 minutes of inactivity - auto log off
        if (empty($_SESSION['created']) || (time() - $_SESSION['created']) > $max_age) {
            return false;
        }
    }

    return true;
}


// Destroy session and remove cookie using same params
function lb_destroy_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    } else {
        // Defensive cookie removal if cookie exists but no active session
        if (isset($_COOKIE[lb_session_name()])) {
            $params = lb_session_cookie_params();
            setcookie(
                lb_session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
    }
}
