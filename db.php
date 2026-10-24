<?php
// =============================================================
//  DATABASE CONNECTION  (Railway-ready)
//  - Reads MYSQL_URL (one variable) OR MYSQLHOST/MYSQLUSER/... variables
//  - Falls back to local XAMPP defaults when no variables are set
//  - Creates all tables + data from ivote_cs.sql automatically on first run
// =============================================================
define('BASE_URL', '/');

$__dbUrl = getenv('MYSQL_URL') ?: (getenv('MYSQL_PUBLIC_URL') ?: (getenv('DATABASE_URL') ?: ''));
$__p = $__dbUrl !== '' ? (parse_url($__dbUrl) ?: []) : [];

define('DB_HOST', $__p['host'] ?? (getenv('MYSQLHOST') ?: 'localhost'));
define('DB_USER', isset($__p['user']) ? urldecode($__p['user']) : (getenv('MYSQLUSER') ?: 'root'));
define('DB_PASS', isset($__p['pass']) ? urldecode($__p['pass']) : (getenv('MYSQLPASSWORD') ?: ''));
define('DB_NAME', (isset($__p['path']) && trim($__p['path'], '/') !== '') ? trim($__p['path'], '/') : (getenv('MYSQLDATABASE') ?: 'ivote_cs'));
define('DB_PORT', (string)($__p['port'] ?? (getenv('MYSQLPORT') ?: '3306')));
unset($__dbUrl, $__p);

function ivote_connect(bool $emulate = false): PDO {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => $emulate,
    ]);
}

/** Imports ivote_cs.sql once, when the database is still empty. Uses its own connection. */
function ivote_install_schema(PDO $check): void {
    if ($check->query("SHOW TABLES LIKE 'users'")->fetchColumn()) return;

    $file = __DIR__ . '/../ivote_cs.sql';
    if (!is_file($file)) throw new RuntimeException('ivote_cs.sql is missing, cannot create the database tables.');

    $pdo = ivote_connect(true);                       // separate connection: the dump changes session settings
    $pdo->query("SELECT GET_LOCK('ivote_install', 60)")->fetchColumn();
    try {
        if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn()) return;   // another request finished first
        try {
            $st = $pdo->query(file_get_contents($file));
            do { /* walk every statement so errors surface */ } while ($st->nextRowset());
        } catch (Throwable $e) {                      // half-imported? clean up so the next request retries
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach (['votes', 'candidates', 'positions', 'elections', 'rate_limits', 'users'] as $t) {
                $pdo->exec("DROP TABLE IF EXISTS `$t`");
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            throw $e;
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('ivote_install')")->fetchColumn();
    }
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = ivote_connect(false);
        ivote_install_schema($pdo);
    }
    return $pdo;
}
