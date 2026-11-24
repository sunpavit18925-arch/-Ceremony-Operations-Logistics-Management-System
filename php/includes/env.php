<?php
/**
 * Safe Environment Loader
 * Loads key-value pairs from .env into putenv() and $_ENV
 */

if (!function_exists('load_env')) {
    function load_env(?string $envFile = null): void
    {
        if ($envFile === null) {
            $envFile = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($envFile) || !is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip surrounding single or double quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false && !array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $val = getenv($key);
        if ($val === false || $val === null || $val === '') {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if ($val === false || $val === null || $val === '') {
            return $default;
        }

        $lower = strtolower((string)$val);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;

        return $val;
    }
}

// Auto load .env if present
load_env();
