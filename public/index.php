<?php
declare(strict_types=1);

// ============================================================
//  public/index.php — single entry point (front controller)
//  Every request lands here; we route by method + URL path.
//
//  Run (built-in server, router mode):
//      php -S localhost:8001 public/index.php
//  (From the project ROOT, one level above public/)
// ============================================================

require dirname(__DIR__) . '/src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (PHP_SAPI === 'cli-server' && $path === '/result.css') {
    header('Content-Type: text/css; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . '/result.css');
    exit;
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDirectory = str_ends_with($scriptName, '/index.php')
    ? rtrim(str_replace('\\', '/', dirname($scriptName)), '/')
    : '';
if ($scriptDirectory !== '' && $scriptDirectory !== '.' && str_starts_with($path, $scriptDirectory)) {
    $path = substr($path, strlen($scriptDirectory)) ?: '/';
}

// --- Routes ---

if (($method === 'GET' || $method === 'POST') && ($path === '/' || $path === '/index.php')) {
    $escape = static fn ($value): string => htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
    $regNumber = trim((string) ($_POST['reg_number'] ?? ''));
    $student = null;
    $lookupState = 'idle';

    if ($method === 'POST') {
        $viewToken = (string) ($_POST['view_token'] ?? '');
        $expectedViewToken = Config::get('RESULTS_VIEW_TOKEN');
        $localDevelopment = PHP_SAPI === 'cli-server'
            && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

        if (($expectedViewToken === null || $expectedViewToken === '') && !$localDevelopment) {
            $lookupState = 'unconfigured';
        } elseif ($expectedViewToken !== null && $expectedViewToken !== ''
            && !hash_equals($expectedViewToken, $viewToken)
        ) {
            $lookupState = 'unauthorized';
        } elseif ($regNumber === '') {
            $lookupState = 'invalid';
        } elseif (strlen($regNumber) > 20) {
            $lookupState = 'invalid';
        } else {
            try {
                $student = repository()->findByRegNumber($regNumber);
                $lookupState = $student === null ? 'missing' : 'found';
            } catch (Throwable $error) {
                error_log('Student result lookup failed: ' . $error->getMessage());
                $lookupState = 'unavailable';
            }
        }
    }

    if ($lookupState === 'missing') {
        http_response_code(404);
    } elseif ($lookupState === 'invalid') {
        http_response_code(400);
    } elseif ($lookupState === 'unavailable' || $lookupState === 'unconfigured') {
        http_response_code(503);
    } elseif ($lookupState === 'unauthorized') {
        http_response_code(403);
    }

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, private');
    header("Content-Security-Policy: default-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

    $waecResults = is_array($student['waec_result'] ?? null) ? $student['waec_result'] : [];
    $utmeResults = is_array($student['utme_result'] ?? null) ? $student['utme_result'] : [];
    $expectedViewToken = Config::get('RESULTS_VIEW_TOKEN');
    $localDevelopment = PHP_SAPI === 'cli-server'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $requiresViewerToken = !$localDevelopment || ($expectedViewToken !== null && $expectedViewToken !== '');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#174d3b">
        <title>Student Result Lookup</title>
        <link rel="stylesheet" href="result.css">
    </head>
    <body>
        <main class="page-shell">
            <header class="site-heading">
                <div class="institution-mark" aria-hidden="true">AR</div>
                <div>
                    <p class="eyebrow">Academic records</p>
                    <h1>Student result lookup</h1>
                </div>
            </header>

            <section class="lookup-panel" aria-labelledby="lookup-title">
                <div>
                    <p class="eyebrow"><?= $localDevelopment ? 'Local development lookup' : 'Authorized record access' ?></p>
                    <h2 id="lookup-title">Find a result</h2>
                </div>
                <form method="post" action="" class="lookup-form">
                    <?php if ($requiresViewerToken): ?>
                    <label for="view-token">Viewer access key</label>
                    <div class="lookup-controls">
                        <input id="view-token" name="view_token" type="password" required
                            autocomplete="current-password" placeholder="Enter access key">
                    </div>
                    <?php endif; ?>
                    <label for="reg-number" class="registration-label">Registration number</label>
                    <div class="lookup-controls">
                        <input id="reg-number" name="reg_number" type="text" maxlength="20"
                            autocomplete="off" required value="<?= $escape($regNumber) ?>"
                            placeholder="Enter registration number">
                        <button type="submit">View result</button>
                    </div>
                </form>
            </section>

            <?php if ($lookupState === 'unconfigured'): ?>
                <p class="notice notice-error" role="alert">Browser result access is not configured. Ask the site administrator to set a dedicated viewer access key.</p>
            <?php elseif ($lookupState === 'unauthorized'): ?>
                <p class="notice notice-error" role="alert">Access could not be verified.</p>
            <?php elseif ($lookupState === 'invalid'): ?>
                <p class="notice notice-error" role="alert">Registration numbers must be 20 characters or fewer.</p>
            <?php elseif ($lookupState === 'missing'): ?>
                <p class="notice notice-error" role="alert">No result was found for that registration number.</p>
            <?php elseif ($lookupState === 'unavailable'): ?>
                <p class="notice notice-error" role="alert">Results are temporarily unavailable. Please try again later.</p>
            <?php endif; ?>

            <?php if ($lookupState === 'found'): ?>
                <article class="result-sheet" aria-labelledby="result-title">
                    <div class="sheet-topline">
                        <span>Academic records office</span>
                        <span>Student copy</span>
                    </div>
                    <header class="sheet-heading">
                        <div class="sheet-seal" aria-hidden="true">AR</div>
                        <p class="eyebrow">Student academic record</p>
                        <h2 id="result-title">Statement of results</h2>
                        <p class="sheet-subtitle">Student examination record</p>
                    </header>

                    <section class="student-details" aria-label="Student details">
                        <div class="detail-item detail-name">
                            <span class="detail-label">Student name</span>
                            <strong><?= $escape($student['full_name'] ?? '') ?></strong>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Registration number</span>
                            <strong><?= $escape($regNumber) ?></strong>
                        </div>
                    </section>

                    <div class="results-grid">
                        <section class="result-section" aria-labelledby="waec-title">
                            <div class="section-heading">
                                <span class="section-index">01</span>
                                <h3 id="waec-title">Secondary examination</h3>
                            </div>
                            <table>
                                <thead><tr><th scope="col">Subject</th><th scope="col">Grade</th></tr></thead>
                                <tbody>
                                <?php if ($waecResults === []): ?>
                                    <tr><td colspan="2" class="empty-result">No subject results recorded</td></tr>
                                <?php else: ?>
                                    <?php foreach ($waecResults as $subject => $grade): ?>
                                        <tr><td><?= $escape(ucwords(str_replace('_', ' ', (string) $subject))) ?></td><td class="score"><?= $escape($grade ?? '—') ?></td></tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </section>

                        <section class="result-section" aria-labelledby="utme-title">
                            <div class="section-heading">
                                <span class="section-index">02</span>
                                <h3 id="utme-title">Entrance examination</h3>
                            </div>
                            <table>
                                <thead><tr><th scope="col">Subject</th><th scope="col">Score</th></tr></thead>
                                <tbody>
                                <?php if ($utmeResults === []): ?>
                                    <tr><td colspan="2" class="empty-result">No subject results recorded</td></tr>
                                <?php else: ?>
                                    <?php foreach ($utmeResults as $subject => $score): ?>
                                        <tr><td><?= $escape(ucwords(str_replace('_', ' ', (string) $subject))) ?></td><td class="score"><?= $escape($score ?? '—') ?></td></tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </section>
                    </div>

                    <div class="summary-row">
                        <span>WAEC aggregate (<?= count($waecResults) ?> subjects)</span>
                        <strong><?= isset($student['aggregate']) && $student['aggregate'] !== null ? $escape(number_format((float) $student['aggregate'], 2) . '%') : 'Not available' ?></strong>
                    </div>
                    <footer class="sheet-footer">
                        <span>Generated <?= $escape(date('F j, Y')) ?></span>
                        <span>Verify this record with the issuing institution.</span>
                    </footer>
                </article>
            <?php endif; ?>
            <footer class="page-footer">Results are displayed as held in the student record.</footer>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Tiny health check, handy for testing connectivity
if ($method === 'GET' && $path === '/health') {
    Response::json(200, 'OK');
}

// The lookup endpoint (our prototype's contract, unchanged)
if ($method === 'POST' && $path === '/api/v1/students/lookup') {

    // 1. Auth — who is asking?
    $token = Config::get('API_BEARER_TOKEN');
    if (!$token) {
        Response::json(500, 'Server misconfiguration: API token not set');
    }
    Auth::requireBearer($token);

    // 2. Input — what are they asking for?
    $content = json_decode(file_get_contents('php://input'), true);
    if (!is_array($content)
        || !isset($content['reg_number'])
        || !is_string($content['reg_number'])
        || trim($content['reg_number']) === ''
        || strlen(trim($content['reg_number'])) > 20
    ) {
        Response::json(400, 'Missing or invalid reg_number');
    }
    $regNumber = trim($content['reg_number']);

    // 3. Data — find it
    $student = repository()->findByRegNumber($regNumber);
    if ($student === null) {
        Response::json(404, 'Reg number not found');
    }

    // 4. Respond
    Response::json(200, 'Successfully retrieved', $student);
}

// Nothing matched
Response::json(404, 'Not found');
