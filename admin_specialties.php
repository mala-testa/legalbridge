<?php
require_once __DIR__ . '/includes/header.php';

requireLogin();
requireAdmin();

$stmt = dbSelect("SELECT id, name FROM specialties ORDER BY id ASC");
$specialties = $stmt->fetchAll();
?>

<h2 class="lb-title center">Manage Specialties</h2>

<div class="center" style="margin-bottom: 20px;">
    <a class="lb-btn" style="max-width:220px;" href="admin_specialty_create.php">Create New Specialty</a>
</div>

<div class="specialties-table-wrapper">
    <table class="specialties-table">
        <thead>
            <tr>
                <th style="width:70px;">ID</th>
                <th>Name</th>
                <th style="width:180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($specialties as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td><?= e($s['name']) ?></td>
                    <td class="actions-cell">

                        <a class="action-btn"
                            href="admin_entity_edit.php?type=specialty&id=<?= (int)$s['id'] ?>">
                            Edit
                        </a>

                        <form method="post"
                            action="admin_entity_delete.php"
                            style="display:inline;">

                            <input type="hidden"
                                name="type"
                                value="specialty">

                            <input type="hidden"
                                name="id"
                                value="<?= (int)$s['id'] ?>">

                            <input type="hidden"
                                name="csrf"
                                value="<?= e(csrfToken()) ?>">

                            <button type="submit"
                                class="action-btn danger"
                                onclick="return confirm('Delete this specialty?');">
                                Delete
                            </button>

                        </form>

                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</main>
</body>

</html>