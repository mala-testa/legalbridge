<?php
require_once __DIR__ . '/includes/header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

if (!verify_session_fingerprint(true)) {
    // optional: log the event
    lb_destroy_session();
    http_response_code(403);
    exit('Session integrity check failed. Please log in again.');
}

// Safe helper to fetch a single count
function safeCount($sql, $params = []) {
    try {
        $stmt = dbSelect($sql, $params);
        $row = $stmt->fetch();
        return $row ? (int)$row['c'] : 0;
    } catch (Exception $e) {
        // Graceful fallback — no internal details exposed
        return 0;
    }
}

$users       = safeCount("SELECT COUNT(*) AS c FROM users");
$clients     = safeCount("SELECT COUNT(*) AS c FROM users WHERE role='client'");
$lawyers     = safeCount("SELECT COUNT(*) AS c FROM users WHERE role='lawyer'");
$admins      = safeCount("SELECT COUNT(*) AS c FROM users WHERE role='admin'");
$requests    = safeCount("SELECT COUNT(*) AS c FROM client_requests");
$specialties = safeCount("SELECT COUNT(*) AS c FROM specialties");
?>

<h2 class="lb-title center">System Overview</h2>

<div class="specialties-table-wrapper">
    <table class="specialties-table">
        <thead>
            <tr>
                <th>Metric</th>
                <th style="width:120px;">Count</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Total Users</td><td><?= $users ?></td></tr>
            <tr><td>Clients</td><td><?= $clients ?></td></tr>
            <tr><td>Lawyers</td><td><?= $lawyers ?></td></tr>
            <tr><td>Admins</td><td><?= $admins ?></td></tr>
            <tr><td>Client Requests</td><td><?= $requests ?></td></tr>
            <tr><td>Specialties</td><td><?= $specialties ?></td></tr>
        </tbody>
    </table>
</div>

</main>
</body>
</html>
