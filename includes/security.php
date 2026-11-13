<?php
/**
 * Security Helpers & Context-aware Sanitizers
 */

if (!function_exists('e')) {
    /**
     * Escape HTML output safely against XSS
     */
    function e(mixed $string): string
    {
        return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('clean_int')) {
    /**
     * Validate and cast integer, return default if invalid
     */
    function clean_int(mixed $value, int $default = 0): int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        return ($filtered !== false) ? (int)$filtered : $default;
    }
}

if (!function_exists('clean_string')) {
    /**
     * Trim and limit string length
     */
    function clean_string(mixed $value, int $maxLength = 255): string
    {
        $str = trim((string)($value ?? ''));
        if (mb_strlen($str, 'UTF-8') > $maxLength) {
            $str = mb_substr($str, 0, $maxLength, 'UTF-8');
        }
        return $str;
    }
}

if (!function_exists('redirect')) {
    /**
     * Perform HTTP redirect and exit immediately
     */
    function redirect(string $url): never
    {
        header("Location: {$url}");
        exit();
    }
}

if (!function_exists('safe_file_basename')) {
    /**
     * Strip path traversal characters from a filename
     */
    function safe_file_basename(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $base = basename($normalized);
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $base);
    }
}
