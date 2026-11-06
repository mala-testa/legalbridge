<?php
require_once 'includes/header.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo "<div class='lb-error'><h3>Access denied.</h3></div>";
    echo "</main></body></html>";
    exit;
}

if (!verify_session_fingerprint(true)) {
    // optional: log the event
    lb_destroy_session();
    http_response_code(403);
    exit('Session integrity check failed. Please log in again.');
}

$user_id = (int) $_SESSION['user_id'];

?>
<h2 class="lb-title center">My Requests</h2>

<div class="lb-section">

<?php
$requests = dbSelect(
    "SELECT r.id, r.case_description, s.name AS specialty_name
     FROM client_requests r
     LEFT JOIN specialties s ON r.specialty_id = s.id
     WHERE r.user_id = :uid
     ORDER BY r.id DESC",
    [':uid' => $user_id]
);

while ($r = $requests->fetch()) {

    $rid   = (int) $r['id'];
    $spec  = e($r['specialty_name'] ?: 'Unknown');
    $desc  = e($r['case_description']);

    echo "
        <div class='lb-card'>
            <h3>Request #{$rid}</h3>
            <p><strong>Specialty:</strong> {$spec}</p>
            <p><strong>Description:</strong> {$desc}</p>
        </div>
    ";
}
?>

</div>

</main>
</body>
</html>
