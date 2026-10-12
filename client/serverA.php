<?php
declare(strict_types=1);

// ============================================================
//  client/serverA.php — the institution-side client.
//  Usage:  php client/serverA.php [reg_number]
//
//  This file lives on the CONSUMER side (separate project/folder
//  from the API). It only needs cURL — nothing else.
// ============================================================

$apiUrl    = getenv('RESULT_API_URL') ?: 'http://localhost:8001/api/v1/students/lookup';
$apiToken  = getenv('API_BEARER_TOKEN');
$regNumber = $argv[1] ?? '7638272HC';

if (!$apiToken) {
    fwrite(STDERR, "Error: set the API_BEARER_TOKEN environment variable.
");
    exit(1);
}

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['reg_number' => $regNumber]),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiToken,
    ],
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    fwrite(STDERR, 'cURL error: ' . curl_error($ch) . PHP_EOL);
    curl_close($ch);
    exit(1);
}

$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode((string) $response, true);

$status = $httpStatus;
if ($status === 200 && is_array($data) && isset($data['status'])) {
    $status = (int) $data['status'];
}

if ($status !== 200) {
    $message = (is_array($data) && isset($data['message'])) ? $data['message'] : $response;
    fwrite(STDERR, "Request failed (HTTP $httpStatus): $message" . PHP_EOL);
    exit(1);
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
