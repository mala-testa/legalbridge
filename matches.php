<?php
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='lb-error'><h3>Access denied.</h3></div>";
    echo "</main></body></html>";
    exit;
}

if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$role    = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

?>
<h2 class="lb-title center">Matches</h2>

<div class="lb-section">

<?php
/* --------------------------------------------------
   CLIENT VIEW
-------------------------------------------------- */
if ($role === 'client') {

    $requests = dbSelect(
        "SELECT id, specialty_id, case_description
         FROM client_requests
         WHERE user_id = :uid",
        [':uid' => $user_id]
    );

    while ($r = $requests->fetch()) {

        $rid  = (int) $r['id'];
        $spec = (int) $r['specialty_id'];
        $desc = e($r['case_description']);

        echo "<div class='lb-card'>";
        echo "<h3>Request #{$rid}</h3>";
        echo "<p>{$desc}</p>";

        // Find matching lawyers
        $lawyers = dbSelect(
            "SELECT u.name, u.email, l.hourly_rate, l.bio
             FROM lawyers l
             JOIN users u ON l.user_id = u.id
             WHERE l.specialty_id = :spec",
            [':spec' => $spec]
        );

        echo "<ul class='lb-list'>";
        while ($lw = $lawyers->fetch()) {
            $name  = e($lw['name']);
            $email = e($lw['email']);
            $rate  = e($lw['hourly_rate']);
            $bio   = e($lw['bio']);

            echo "
                <li class='lb-list-item'>
                    <strong>{$name}</strong> ({$email})<br>
                    <span>Rate: \${$rate}/hr</span><br>
                    <span>Bio: {$bio}</span>
                </li>
            ";
        }
        echo "</ul>";
        echo "</div>";
    }
}

/* --------------------------------------------------
   LAWYER VIEW
-------------------------------------------------- */
elseif ($role === 'lawyer') {

    // Get lawyer specialty
    $stmt = dbSelect(
        "SELECT specialty_id FROM lawyers WHERE user_id = :uid",
        [':uid' => $user_id]
    );
    $row = $stmt->fetch();
    $spec = (int) $row['specialty_id'];

    // Find matching client requests
    $requests = dbSelect(
        "SELECT r.id, r.case_description, u.name AS client_name, u.email AS client_email
         FROM client_requests r
         JOIN users u ON r.user_id = u.id
         WHERE r.specialty_id = :spec",
        [':spec' => $spec]
    );

    echo "<ul class='lb-list'>";
    while ($r = $requests->fetch()) {

        $rid    = (int) $r['id'];
        $desc   = e($r['case_description']);
        $cname  = e($r['client_name']);
        $cemail = e($r['client_email']);

        echo "
            <li class='lb-list-item'>
                <strong>Request #{$rid}</strong><br>
                <strong>Client:</strong> {$cname} ({$cemail})<br>
                <strong>Description:</strong> {$desc}
            </li>
        ";
    }
    echo "</ul>";
}

/* --------------------------------------------------
   UNKNOWN ROLE
-------------------------------------------------- */
else {
    echo "<p class='lb-error center'>Unknown role.</p>";
}
?>

</div>

</main>
</body>
</html>
