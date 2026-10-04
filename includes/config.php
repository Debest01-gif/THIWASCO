<?php
/**
 * THIWASCO - Database Configuration
 */

// Environment / Database Configuration
// Default to SQLite - works on Render/Docker without any configuration.
// Set DB_CONNECTION=mysql or DB_CONNECTION=pgsql to use a remote database.
$envDbConn = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'sqlite');
$envDbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
$envDbPort = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
$envDbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
$envDbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? '');
$envDbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'thiwasco_db');
$envDbCharset = getenv('DB_CHARSET') ?: ($_ENV['DB_CHARSET'] ?? 'utf8mb4');

// Parse DATABASE_URL only when explicitly using MySQL or PostgreSQL (not SQLite)
if ($envDbConn !== 'sqlite') {
    $databaseUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? '');
    if (!empty($databaseUrl)) {
        $dbParts = parse_url($databaseUrl);
        if ($dbParts) {
            $envDbConn = ($dbParts['scheme'] === 'postgres' || $dbParts['scheme'] === 'postgresql') ? 'pgsql' : 'mysql';
            $envDbHost = $dbParts['host'] ?? $envDbHost;
            $envDbPort = $dbParts['port'] ?? ($envDbConn === 'pgsql' ? '5432' : '3306');
            $envDbUser = $dbParts['user'] ?? $envDbUser;
            $envDbPass = $dbParts['pass'] ?? $envDbPass;
            $envDbName = ltrim($dbParts['path'] ?? '', '/') ?: $envDbName;
        }
    }
}

if (!defined('DB_CONNECTION')) define('DB_CONNECTION', $envDbConn);
if (!defined('DB_HOST')) define('DB_HOST', $envDbHost);
if (!defined('DB_PORT')) define('DB_PORT', $envDbPort);
if (!defined('DB_USER')) define('DB_USER', $envDbUser);
if (!defined('DB_PASS')) define('DB_PASS', $envDbPass);
if (!defined('DB_NAME')) define('DB_NAME', $envDbName);
if (!defined('DB_CHARSET')) define('DB_CHARSET', $envDbCharset);

// Application Settings
if (!defined('APP_NAME')) define('APP_NAME', 'THIWASCO MIS');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Africa/Nairobi');

// Auto-detect APP_URL
if (!defined('APP_URL')) {
    $envUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');
    if (!empty($envUrl)) {
        define('APP_URL', rtrim($envUrl, '/'));
    } else {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseFolder = '';
        if (stripos($scriptName, '/THIWASCO') === 0) {
            $baseFolder = '/THIWASCO';
        }
        define('APP_URL', $protocol . $host . $baseFolder);
    }
}

// Session
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'THIWASCO_SESSION');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 28800); // 8 hours

// File Upload Paths
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', __DIR__ . '/../uploads/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', APP_URL . '/uploads/');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Ensure uploads directories exist
$uploadSubDirs = ['meter_photos', 'inspection_photos', 'profile_photos'];
foreach ($uploadSubDirs as $sub) {
    $p = UPLOAD_PATH . $sub;
    if (!is_dir($p)) {
        @mkdir($p, 0777, true);
    }
}

// Security
if (!defined('SALT')) define('SALT', 'ThiwaWater2026!@#$%SecureKey');
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOCKOUT_MINUTES')) define('LOCKOUT_MINUTES', 30);

date_default_timezone_set(APP_TIMEZONE);

/**
 * Database Connection (Singleton)
 */
class Database {
    private static $instance = null;
    private $pdo;
    private $driver = 'mysql';

    private function __construct() {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 2,
        ];

        $sqliteFile = __DIR__ . '/../database/thiwasco.sqlite';

        // Check if explicitly sqlite or if we should try MySQL first
        if (DB_CONNECTION === 'sqlite') {
            $this->initSqlite($sqliteFile, $options);
            return;
        }

        try {
            if (DB_CONNECTION === 'pgsql') {
                $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                $this->driver = 'pgsql';
            } else {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                $this->driver = 'mysql';
            }
        } catch (PDOException $e) {
            // If MySQL fails and sqlite file exists or can be created, fallback gracefully
            if (file_exists($sqliteFile) || is_writable(dirname($sqliteFile))) {
                $this->initSqlite($sqliteFile, $options);
            } else {
                die(json_encode([
                    'error' => 'Database connection failed: ' . $e->getMessage(),
                    'tip' => 'Please configure DB_HOST, DB_USER, DB_PASS, DB_NAME or use DB_CONNECTION=sqlite'
                ]));
            }
        }
    }

    private function initSqlite($sqliteFile, $options) {
        $dbDir = dirname($sqliteFile);
        if (!is_dir($dbDir)) {
            @mkdir($dbDir, 0777, true);
        }
        @chmod($dbDir, 0777);
        if (file_exists($sqliteFile)) {
            @chmod($sqliteFile, 0666);
        }

        $this->driver = 'sqlite';
        $this->pdo = new PDO("sqlite:" . $sqliteFile, null, null, $options);
        $this->pdo->exec("PRAGMA foreign_keys = ON;");

        // Verify if core tables exist, if not auto-initialize
        try {
            $tableCheck = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
            if (!$tableCheck) {
                require_once __DIR__ . '/../database/init_sqlite.php';
                if (function_exists('initSqliteDatabase')) {
                    initSqliteDatabase($this->pdo);
                }
            }
        } catch (Exception $e) {
            // Ignore if check fails
        }

        // Register custom SQL functions for MySQL syntax compatibility
        $this->pdo->sqliteCreateFunction('NOW', function() { return date('Y-m-d H:i:s'); });
        $this->pdo->sqliteCreateFunction('CURDATE', function() { return date('Y-m-d'); });
        $this->pdo->sqliteCreateFunction('IF', function($cond, $trueVal, $falseVal) { return $cond ? $trueVal : $falseVal; });
        $this->pdo->sqliteCreateFunction('IFNULL', function($val, $default) { return $val !== null ? $val : $default; });
        $this->pdo->sqliteCreateFunction('GREATEST', function(...$args) { return count($args) ? max($args) : null; });
        $this->pdo->sqliteCreateFunction('LEAST', function(...$args) { return count($args) ? min($args) : null; });
        $this->pdo->sqliteCreateFunction('CONCAT', function(...$args) { return implode('', $args); });
        $this->pdo->sqliteCreateFunction('DATE_FORMAT', function($date, $format) {
            if (!$date) return null;
            $ts = strtotime($date);
            if ($ts === false) return null;
            $phpFormat = str_replace(
                ['%Y', '%y', '%m', '%c', '%d', '%e', '%H', '%h', '%i', '%s', '%M', '%b', '%W', '%a'],
                ['Y',  'y',  'm',  'n',  'd',  'j',  'H',  'h',  'i',  's',  'F',  'M',  'l',  'D'],
                $format
            );
            return date($phpFormat, $ts);
        });
        $this->pdo->sqliteCreateFunction('DATEDIFF', function($date1, $date2) {
            if (!$date1 || !$date2) return null;
            $t1 = strtotime($date1);
            $t2 = strtotime($date2);
            if ($t1 === false || $t2 === false) return null;
            return (int)round(($t1 - $t2) / 86400);
        });
        $this->pdo->sqliteCreateFunction('YEAR', function($date) {
            return $date ? date('Y', strtotime($date)) : null;
        });
        $this->pdo->sqliteCreateFunction('MONTH', function($date) {
            return $date ? date('n', strtotime($date)) : null;
        });
        $this->pdo->sqliteCreateFunction('DAY', function($date) {
            return $date ? date('j', strtotime($date)) : null;
        });
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function getDriver() {
        return $this->driver;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}

// Global DB shortcut
function db() {
    return Database::getInstance();
}

/**
 * Get system setting
 */
function getSetting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        try {
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM system_settings");
            $settings = array_column($rows, 'setting_value', 'setting_key');
        } catch (Exception $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}
