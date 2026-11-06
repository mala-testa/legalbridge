<?php
require_once __DIR__ . '/includes/header.php';

requireLogin();
requireAdmin();
?>

<h2 class="lb-title center">Admin Dashboard</h2>

<div class="admin-grid">

    <a class="admin-tile" href="admin_users.php">
        <div class="tile-icon">👥</div>
        <div class="tile-title">Users</div>
        <div class="tile-desc">Manage all platform users</div>
    </a>

    <a class="admin-tile" href="admin_lawyers.php">
        <div class="tile-icon">⚖️</div>
        <div class="tile-title">Lawyers</div>
        <div class="tile-desc">Manage lawyer accounts</div>
    </a>

    <a class="admin-tile" href="admin_clients.php">
        <div class="tile-icon">📄</div>
        <div class="tile-title">Clients</div>
        <div class="tile-desc">Manage client profiles</div>
    </a>

    <a class="admin-tile" href="admin_specialties.php">
        <div class="tile-icon">📚</div>
        <div class="tile-title">Specialties</div>
        <div class="tile-desc">Edit legal specialties</div>
    </a>

    <a class="admin-tile" href="system_overview.php">
        <div class="tile-icon">🛠️</div>
        <div class="tile-title">System</div>
        <div class="tile-desc">System status & tools</div>
    </a>

</div>

</main>
</body>
</html>
