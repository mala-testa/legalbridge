<?php
// dashboard.php
require_once __DIR__ . '/includes/header.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo "<div class='lb-error'><h3>You must be logged in.</h3></div>";
    echo "</main></body></html>";
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? '';

// If admin, redirect to the admin area
if ($role === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}
?>
<h2 class="lb-title center">Dashboard</h2>

<?php
switch ($role) {

    case 'client':
        ?>
        <div class="lb-section center">
            <a class="lb-btn" href="create_request.php">Create New Request</a>
            <a class="lb-btn" href="my_requests.php">My Requests</a>
            <a class="lb-btn" href="matches.php">Matches</a>
        </div>
        <?php
        break;

    case 'lawyer':

        // Fetch lawyer specialty safely
        $stmt = dbSelect(
            "SELECT specialty_id FROM lawyers WHERE user_id = :uid",
            [':uid' => $user_id]
        );
        $row = $stmt->fetch();
        $specialty_id = $row['specialty_id'] ?? null;

        $specName = 'Unknown';
        if ($specialty_id) {
            $specStmt = dbSelect(
                "SELECT name FROM specialties WHERE id = :id",
                [':id' => $specialty_id]
            );
            $specRow = $specStmt->fetch();
            if ($specRow) {
                $specName = $specRow['name'];
            }
        }
        ?>

        <div class="lb-section center">
            <p><strong>Specialty:</strong> <?= htmlspecialchars($specName) ?></p>
            <a class="lb-btn" href="my_cases.php">My Cases</a>
            <a class="lb-btn" href="matches.php">Matches</a>
            <a class="lb-btn" href="profile.php">Edit Profile</a>
        </div>

        <?php
        break;

    default:
        echo "<p class='center lb-error'>Unknown role.</p>";
}
?>

</main>
</body>
</html>
