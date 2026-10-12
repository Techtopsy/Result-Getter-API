<?php
declare(strict_types=1);

// ============================================================
//  src/bootstrap.php — wires everything together.
//  Loaded once by public/index.php at the start of every request.
// ============================================================

require __DIR__ . '/Config.php';
require __DIR__ . '/Response.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/StudentRepository.php';

Config::load(dirname(__DIR__) . '/.env');   // .env sits OUTSIDE the web root

/**
 * Returns the repository, building it on first use.
 * Switching to the database = changing one line in .env:
 *   REPOSITORY_DRIVER=memory   (prototype, no DB needed)
 *   REPOSITORY_DRIVER=pdo      (real MySQL)
 */
function repository(): StudentRepository
{
    static $repo = null;
    if ($repo === null) {
        if (Config::get('REPOSITORY_DRIVER', 'memory') === 'pdo') {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;dbname=%s;charset=utf8mb4',
                    Config::get('DB_HOST'),
                    Config::get('DB_NAME')
                ),
                Config::get('DB_USER'),
                Config::get('DB_PASS'),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            $repo = new PdoStudentRepository($pdo);
        } else {
            $repo = new InMemoryStudentRepository();
        }
    }
    return $repo;
}
