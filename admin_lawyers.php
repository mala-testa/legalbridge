<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

lb_secure_session_start();

/*
|--------------------------------------------------------------------------
| Administrator authentication
|--------------------------------------------------------------------------
*/

requireLogin();
requireAdmin();

/*
|--------------------------------------------------------------------------
| Load lawyer accounts
|--------------------------------------------------------------------------
|
| Requiring u.role = 'lawyer' ensures administrator or client accounts
| cannot accidentally appear in this list.
|
*/

$stmt = dbSelect(
    "SELECT
        u.id,
        u.name,
        u.email,
        u.active AS user_active,
        l.specialty_id,
        l.active AS lawyer_active,
        s.name AS specialty
     FROM users u
     INNER JOIN lawyers l
        ON u.id = l.user_id
     INNER JOIN specialties s
        ON l.specialty_id = s.id
     WHERE u.role = 'lawyer'
     ORDER BY u.id ASC"
);

$lawyers = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Load HTML after authentication and database processing
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/header.php';

?>

<h2 class="lb-title center">
    Lawyers
</h2>

<div class="specialties-table-wrapper">

    <?php if (empty($lawyers)): ?>

        <div class="lb-card">
            <p>No lawyer accounts were found.</p>
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

                    <th>
                        Specialty
                    </th>

                    <th style="width: 100px;">
                        Status
                    </th>

                    <th style="width: 180px;">
                        Actions
                    </th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($lawyers as $lawyer): ?>

                    <?php
                    $lawyerId =
                        (int)$lawyer['id'];

                    $isActive =
                        (int)$lawyer['user_active'] === 1 &&
                        (int)$lawyer['lawyer_active'] === 1;
                    ?>

                    <tr>

                        <td>
                            <?= $lawyerId ?>
                        </td>

                        <td>
                            <?= e($lawyer['name'] ?? '') ?>
                        </td>

                        <td>
                            <?= e($lawyer['email'] ?? '') ?>
                        </td>

                        <td>
                            <?= e(
                                $lawyer['specialty']
                                    ?? 'Unknown'
                            ) ?>
                        </td>

                        <td>
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </td>

                        <td class="actions-cell">

                            <a
                                class="action-btn"
                                href="admin_entity_edit.php?type=lawyer&amp;id=<?= $lawyerId ?>">
                                Edit
                            </a>

                            <form
                                method="post"
                                action="admin_entity_delete.php"
                                style="display: inline;">

                                <input
                                    type="hidden"
                                    name="type"
                                    value="lawyer">

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $lawyerId ?>">

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrfToken()) ?>">

                                <button
                                    type="submit"
                                    class="action-btn danger"
                                    onclick="return confirm('Delete this lawyer account? This will also delete the lawyer profile and all messages involving this user.');">
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
