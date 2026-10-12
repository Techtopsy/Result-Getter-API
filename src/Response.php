<?php
declare(strict_types=1);

// ============================================================
//  src/Response.php — one consistent way to answer every
//  request: real HTTP status code + JSON body, then stop.
// ============================================================

final class Response
{
    public static function json(int $httpCode, string $message, ?array $data = null): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');

        $body = ['status' => $httpCode, 'message' => $message];
        if ($data !== null) {
            $body['data'] = $data;
        }

        echo json_encode($body, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
