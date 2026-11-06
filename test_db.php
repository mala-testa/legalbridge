<?php

require_once __DIR__ . '/includes/functions.php';

try {

    $stmt = dbSelect('SELECT 1 AS test');

    $result = $stmt->fetch();

    echo '<h2>Database Connection Test</h2>';

    echo '<pre>';
    print_r($result);
    echo '</pre>';

    echo '<p style="color:green;">Database connection successful.</p>';

} catch (Throwable $e) {

    echo '<p style="color:red;">Database connection failed.</p>';

    echo '<pre>';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '</pre>';
}