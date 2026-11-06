<?php
require_once 'includes/header.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo "<div class='lb-error'><h3>Access denied.</h3></div>";
    echo "</main></body></html>";
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error = "";
$case_description = "";
$specialty_id = 0;

// Load specialties
$specialties = dbSelect("SELECT id, name FROM specialties WHERE active = 1 ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $specialty_id     = (int) ($_POST['specialty'] ?? 0);
    $case_description = sanitizeString($_POST['case_description'] ?? '');

    if ($specialty_id === 0 || $case_description === "") {
        $error = "All fields are required.";
    } else {

        dbExecute(
            "INSERT INTO client_requests (user_id, specialty_id, case_description)
             VALUES (:uid, :spec, :desc)",
            [
                ':uid'  => $user_id,
                ':spec' => $specialty_id,
                ':desc' => $case_description
            ]
        );

        echo "<div class='lb-form-container center'>
                <h4>Request submitted!</h4>
                <a class='lb-btn' href='dashboard.php'>Return to Dashboard</a>
              </div>
              </main></body></html>";
        exit;
    }
}

?>
<h2 class="lb-title center">Create New Request</h2>

<div class="lb-form-container">

    <?php if ($error): ?>
        <div class="lb-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="create_request.php" class="lb-form">

        <label for="specialty">Specialty</label>
        <select id="specialty" name="specialty" required>
            <option value="">Select a specialty</option>
            <?php while ($row = $specialties->fetch()): ?>
                <option value="<?= (int)$row['id'] ?>">
                    <?= e($row['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="case_description">Case Description</label>
        <textarea id="case_description"
                  name="case_description"
                  rows="5"
                  required><?= e($case_description) ?></textarea>

        <button type="submit" class="lb-btn">Submit Request</button>

    </form>
</div>

</main>
</body>
</html>
