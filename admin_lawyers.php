<?php
require_once __DIR__ . '/includes/header.php';

requireLogin();
requireAdmin();


$stmt = dbSelect("
    SELECT u.id, u.name, u.email, l.specialty_id, s.name AS specialty
    FROM users u
    JOIN lawyers l ON u.id = l.user_id
    JOIN specialties s ON l.specialty_id = s.id
    ORDER BY u.id ASC
");
$lawyers = $stmt->fetchAll();
?>



<h2 class="lb-title center">Lawyers</h2>



<div class="specialties-table-wrapper">
    <table class="specialties-table">
        <thead>
            <tr>
                <th style="width:70px;">ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Specialty</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lawyers as $l): ?>
                <tr>
                    <td><?= (int)$l['id'] ?></td>
                    <td><?= e($l['name']) ?></td>
                    <td><?= e($l['email']) ?></td>
                    <td><?= e($l['specialty']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>



</main>
</body>

</html>