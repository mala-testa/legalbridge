<?php

require_once __DIR__ . '/includes/header.php';
//require_once __DIR__ . '/includes/admin_auth.php';

requireLogin();
requireAdmin();

$allowedTypes = [
    'user',
    'client',
    'lawyer',
    'specialty'
];

$type = $_GET['type'] ?? '';

if (!in_array($type, $allowedTypes, true)) {
    exit('Invalid type');
}

$id   = (int)($_GET['id'] ?? 0);
$error = '';

if ($id <= 0) {
    exit('Invalid ID');
}

/*
|--------------------------------------------------------------------------
| Load record
|--------------------------------------------------------------------------
*/

if ($type === 'specialty') {

    $stmt = dbSelect(
        "SELECT id, name
         FROM specialties
         WHERE id = ?",
        [$id]
    );

    $user = $stmt->fetch();
} else {

    $stmt = dbSelect(
        "SELECT id, name, email, role
         FROM users
         WHERE id = ?",
        [$id]
    );

    $user = $stmt->fetch();

    if ($user && $type === 'lawyer') {

        $lawyer = dbSelect(
            "SELECT specialty_id
             FROM lawyers
             WHERE user_id = ?",
            [$id]
        )->fetch();

        $user['specialty_id'] = $lawyer['specialty_id'] ?? 0;

        $specialties = dbSelect(
            "SELECT id, name
             FROM specialties
             ORDER BY name ASC"
        )->fetchAll();
    }
}

if (!$user) {
    exit('Record not found');
}

/*
|--------------------------------------------------------------------------
| Save Changes
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrfValidate($_POST['csrf'] ?? null)) {
        exit('Invalid CSRF token');
    }


    if ($type === 'specialty') {

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {

            $error = 'Specialty name is required.';

        } else {

            dbExecute(
                "UPDATE specialties
                 SET name = ?
                 WHERE id = ?",
                [$name, $id]
            );

            header('Location: admin_specialties.php');
            exit;
        }
    } else {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (
            $name === '' || $email === '') {

            $error = 'Name and email are required.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Invalid email address.';

        } else {

            dbExecute(
                "UPDATE users
                 SET name = ?, email = ?
                 WHERE id = ?",
                [
                    $name,
                    $email,
                    $id
                ]
            );

            if ($type === 'lawyer') {

                $specialtyId = (int)(
                    $_POST['specialty_id'] ?? 0
                );

                dbExecute(
                    "UPDATE lawyers
                     SET specialty_id = ?
                     WHERE user_id = ?",
                    [
                        $specialtyId,
                        $id
                    ]
                );
            }

            header(
                'Location: ' .
                    match ($type) {
                        'lawyer' => 'admin_lawyers.php',
                        'client' => 'admin_clients.php',
                        default  => 'admin_users.php'
                    }
            );

            exit;
        }
    }
}

?>

<h2 class="lb-title center">
    Edit <?= e(ucfirst($type)) ?>
</h2>

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

    <?php if ($type === 'specialty'): ?>

        <label for="name">Specialty Name</label>

        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            required
            value="<?= e($_POST['name'] ?? $user['name']) ?>"

    <?php else: ?>

        <label for="name">Name</label>

        <input
            type="text"
            id="name"
            name="name"
            required
            value="<?= e($_POST['name'] ?? $user['name']) ?>"

        <label for="email">Email</label>

        <input
            type="email"
            id="email"
            name="email"
            required
            value="<?= e($_POST['email'] ?? $user['email']) ?>"

        <?php if ($type === 'lawyer'): ?>

            <label for="specialty_id">
                Specialty
            </label>

            <select
                id="specialty_id"
                name="specialty_id"
                required>

                <?php foreach ($specialties as $s): ?>
                    <option
                        value="<?= (int)$s['id'] ?>"
                        <?= ((int)$user['specialty_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                        <?= e($s['name']) ?>
                    </option>
                <?php endforeach; ?>

            </select>

        <?php endif; ?>

    <?php endif; ?>

    <div style="margin-top:20px;">

        <button
            type="submit"
            class="lb-btn">
            Save Changes
        </button>

        <a
            class="lb-btn secondary"
            href="<?=
                    match ($type) {
                        'lawyer'    => 'admin_lawyers.php',
                        'client'    => 'admin_clients.php',
                        'specialty' => 'admin_specialties.php',
                        default     => 'admin_users.php'
                    }
                    ?>">
                    Cancel 
                </a>
            </div>
        </form>
    </main>
</body>
</html>