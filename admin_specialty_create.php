<?php

require_once __DIR__ . '/includes/header.php';

requireLogin();
requireAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrfValidate($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {

        $error = 'Specialty name is required.';

    } else {

        $exists = dbSelect(
            "SELECT id
             FROM specialties
             WHERE LOWER(name) = LOWER(?)",
            [$name]
        )->fetch();

        if ($exists) {

            $error = 'That specialty already exists.';

        } else {

                dbExecute(
                "INSERT INTO specialties (name)
                 VALUES (?)",
                [$name]
            );

            $newId = $pdo->lastInsertId();

            header('Location: admin_specialties.php?created=$newId');
            exit;
        }
    }
}

?>

<h2 class="lb-title center">Create Specialty</h2>

<?php if ($error): ?>
    <div class="error-message">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" class="lb-form">

    <input
        type="hidden"
        name="csrf"
        value="<?= e(csrfToken()) ?>">

    <div class="form-group">

        <label for="name">
            Specialty Name
        </label>

        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            required
            value="<?= e($_POST['name'] ?? '') ?>">

    </div>

    <div class="center" style="margin-top:20px;">

        <button
            type="submit"
            class="lb-btn">
            Create Specialty
        </button>

        <a
            href="admin_specialties.php"
            class="lb-btn secondary">
            Cancel
        </a>

    </div>

</form>

</main>
</body>
</html>