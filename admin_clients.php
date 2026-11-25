<?php
require_once __DIR__ . '/includes/header.php';

requireLogin();
requireAdmin();

$stmt = dbSelect("
    SELECT u.id, u.name, u.email
    FROM users u
    WHERE u.role = 'client'
    ORDER BY u.id ASC
");
$clients = $stmt->fetchAll();
?>

<h2 class="lb-title center">Manage Clients</h2>

<div class="specialties-table-wrapper">
    <table class="specialties-table">
        <thead>
            <tr>
                <th style="width:70px;">ID</th>
                <th>Name</th>
                <th>Email</th>
                <th style="width:180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td class="actions-cell">

                        <a class="action-btn"
                            href="admin_entity_edit.php?type=client&id=<?= (int)$c['id'] ?>">
                            Edit
                        </a>

                        <form method="post"
                            action="admin_entity_delete.php"
                            style="display:inline;">

                            <input type="hidden"
                                name="type"
                                value="client">

                            <input type="hidden"
                                name="id"
                                value="<?= (int)$c['id'] ?>">

                            <input type="hidden"
                                name="csrf"
                                value="<?= e(csrfToken()) ?>">

                            <button type="submit"
                                class="action-btn danger"
                                onclick="return confirm('Delete this client?');">
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
