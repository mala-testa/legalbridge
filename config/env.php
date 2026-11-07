<?php

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException('.env file not found');
    }

    $lines = file(
        $path,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    foreach ($lines as $line) {

        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);

        $name = trim($name);
        $value = trim($value);
        $value = trim($value, "\"'");

        if (!isset($_ENV[$name])) {

            $_ENV[$name] = $value;

            putenv(
                sprintf(
                    '%s=%s',
                    $name,
                    $value
                )
            );
        }
    }
}