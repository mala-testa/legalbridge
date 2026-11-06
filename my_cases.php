<?php
require_once 'includes/header.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lawyer') {
    echo "<div class='lb-error'><h3>Access denied.</h3></div>";
    echo "</main></body></html>";
    exit;
}

$user_id = (int) $_SESSION['user_id'];

?>
<h2 class="lb-title center">My Cases</h2>

<div class="lb-section">

<?php
// Get lawyer specialty
$stmt = dbSelect(
    "SELECT specialty_id FROM lawyers WHERE user_id = :uid",
    [':uid' => $user_id]
);
$row = $stmt->fetch();
$specialty_id = (int) $row['specialty_id'];

// Get matching cases
$cases = dbSelect(
    "SELECT r.id, r.case_description, u.name AS client_name, u.email AS client_email
     FROM client_requests r
     JOIN users u ON r.user_id = u.id
     WHERE r.specialty_id = :spec
     ORDER BY r.id DESC",
    [':spec' => $specialty_id]
);

while ($c = $cases->fetch()) {

    $cid    = (int) $c['id'];
    $cname  = e($c['client_name']);
    $cemail = e($c['client_email']);
    $desc   = e($c['case_description']);

    echo "
        <div class='lb-card'>
            <h3>Case #{$cid}</h3>
            <p><strong>Client:</strong> {$cname} ({$cemail})</p>
            <p><strong>Description:</strong> {$desc}</p>
        </div>
    ";
}
?>

</div>

</main>
</body>
</html>
