<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

/*
|--------------------------------------------------------------------------
| Authentication and authorization
|--------------------------------------------------------------------------
*/

requireLogin();
requireAdmin();

/*
|--------------------------------------------------------------------------
| Load non-administrator users only
|--------------------------------------------------------------------------
|
| Administrator accounts are excluded from the query, so their names,
| email addresses, IDs, and action buttons are never rendered.
|
*/

$stmt = dbSelect(
    "SELECT
        id,
        name,
        email,
        role
     FROM users
     WHERE role IN ('client', 'lawyer')
     ORDER BY id ASC"
);

$users = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Render page
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    Manage Users
</h2>

<div class="specialties-table-wrapper">

    <?php if (empty($users)): ?>

        <div class="lb-card">
            <p>No client or lawyer accounts were found.</p>
        </div>

    <?php else: ?>

        <table class="specialties-table">

            <thead>
                <tr>
                    <th style="width: 70px;">
                        ID
                    </th>

                    <th>
                        Name
                    </th>

                    <th>
                        Email
                    </th>

                    <th style="width: 120px;">
                        Role
                    </th>

                    <th style="width: 180px;">
                        Actions
                    </th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($users as $user): ?>

                    <?php
                    $user_id   = (int)$user['id'];
                    $user_role = $user['role'] ?? '';
                    ?>

                    <?php
                    /*
                     * Defensive check in addition to the SQL filter.
                     * If an unexpected role is returned, do not display it.
                     */
                    if (!in_array(
                        $user_role,
                        ['client', 'lawyer'],
                        true
                    )) {
                        continue;
                    }
                    ?>

                    <tr>

                        <td>
                            <?= $user_id ?>
                        </td>

                        <td>
                            <?= e($user['name'] ?? '') ?>
                        </td>

                        <td>
                            <?= e($user['email'] ?? '') ?>
                        </td>

                        <td>
                            <?= e(ucfirst($user_role)) ?>
                        </td>

                        <td class="actions-cell">

                            <a
                                class="action-btn"
                                href="admin_entity_edit.php?type=user&amp;id=<?= $user_id ?>">
                                Edit
                            </a>

                            <form
                                method="post"
                                action="admin_entity_delete.php"
                                style="display: inline;">

                                <input
                                    type="hidden"
                                    name="type"
                                    value="user">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $user_id ?>">

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrfToken()) ?>">

                                <button
                                    type="submit"
                                    class="action-btn danger"
                                    onclick="return confirm('Delete this user?');">
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</div>

</main>
</body>

</html>