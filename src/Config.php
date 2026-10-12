<?php
declare(strict_types=1);

// ============================================================
//  src/Config.php — reads settings from .env or real
//  environment variables. Secrets never live in code.
// ============================================================

final class Config
{
    private static array $vars = [];

    /** Parse a simple KEY=VALUE .env file. */
    public static function load(string $envPath): void
    {
        if (!is_file($envPath)) {
            return;
        }
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                self::$vars[trim($parts[0])] = trim($parts[1]);
            }
        }
    }

    /** Priority: real env var > .env file > default. */
    public static function get(string $key, ?string $default = null): ?string
    {
        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }
        return self::$vars[$key] ?? $default;
    }
}
