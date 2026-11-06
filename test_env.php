<?php

declare(strict_types=1);

require_once __DIR__ . '/config/env.php';

try {

    loadEnv(__DIR__ . '/.env');

    echo '<h2>Environment Test</h2>';

    echo '<pre>';

    echo 'DB_HOST: ' . (getenv('DB_HOST') ?: 'NOT FOUND') . PHP_EOL;
    echo 'DB_NAME: ' . (getenv('DB_NAME') ?: 'NOT FOUND') . PHP_EOL;
    echo 'DB_USER: ' . (getenv('DB_USER') ?: 'NOT FOUND') . PHP_EOL;

    $password = getenv('DB_PASS');

    echo 'DB_PASS: ' .
        ($password !== false ? str_repeat('*', strlen($password)) : 'NOT FOUND')
        . PHP_EOL;

    echo 'DB_CHARSET: ' .
        (getenv('DB_CHARSET') ?: 'NOT FOUND')
        . PHP_EOL;

    echo '</pre>';

    echo '<p style="color:green;">Environment loaded successfully.</p>';

} catch (Throwable $e) {

    echo '<p style="color:red;">Error:</p>';

    echo '<pre>';
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '</pre>';
}