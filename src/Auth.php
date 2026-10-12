<?php
declare(strict_types=1);

// ============================================================
//  src/Auth.php — Bearer-token checks.
// ============================================================

final class Auth
{
    public static function requireBearer(string $expectedToken): void
    {
        $expected = 'Bearer ' . $expectedToken;
        if (!hash_equals($expected, self::bearerToken())) {
            Response::json(401, 'Unauthorized API key');
        }
    }

    private static function bearerToken(): string
    {
        // Reads Authorization across SAPIs; Apache fallback documented here.
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    return (string) $value;
                }
            }
        }
        return $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
    }
}
