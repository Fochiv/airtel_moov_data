<?php
declare(strict_types=1);

function app_config(string $key, mixed $default = null): mixed
{
    static $local = null;
    if ($local === null) {
        $localFile = dirname(__DIR__) . '/config.local.php';
        $local = is_file($localFile) ? require $localFile : [];
        if (!is_array($local)) {
            $local = [];
        }
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    return $local[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = strtolower((string) app_config('DB_DRIVER', 'sqlite'));
    if ($driver === 'mysql') {
        $host = (string) app_config('DB_HOST', '');
        $name = (string) app_config('DB_NAME', '');
        $user = (string) app_config('DB_USER', '');
        $password = (string) app_config('DB_PASSWORD', '');
        if ($host === '' || $name === '' || $user === '') {
            throw new RuntimeException('La configuration MySQL est incomplète.');
        }
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }

    if ($driver !== 'sqlite') {
        throw new RuntimeException('DB_DRIVER doit être sqlite ou mysql.');
    }

    $directory = dirname(__DIR__) . '/storage';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible de préparer le stockage local.');
    }
    $pdo = new PDO('sqlite:' . $directory . '/gooddeal.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

function initialize_database(): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    $pdo = db();
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $text = $mysql ? 'LONGTEXT' : 'TEXT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id $id,
        `operator` VARCHAR(16) NOT NULL,
        price INTEGER NOT NULL,
        data_volume VARCHAR(100) NOT NULL,
        validity VARCHAR(100) NOT NULL,
        description VARCHAR(255) NOT NULL DEFAULT '',
        active INTEGER NOT NULL DEFAULT 1,
        created_at VARCHAR(32) NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id $id,
        client_reference VARCHAR(64) NOT NULL UNIQUE,
        package_id BIGINT NOT NULL,
        `operator` VARCHAR(16) NOT NULL,
        package_name VARCHAR(100) NOT NULL,
        amount INTEGER NOT NULL,
        customer_phone VARCHAR(32) NOT NULL,
        payment_phone VARCHAR(32) NOT NULL,
        payment_method VARCHAR(40) NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'pending_payment',
        payment_intent_id VARCHAR(100) NULL UNIQUE,
        provider_reference VARCHAR(120) NULL,
        payment_reference VARCHAR(120) NULL,
        redirect_url VARCHAR(2048) NULL,
        created_at VARCHAR(32) NOT NULL,
        paid_at VARCHAR(32) NULL,
        delivered_at VARCHAR(32) NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_lookup_attempts (
        id $id,
        ip_hash VARCHAR(64) NOT NULL,
        attempted_at INTEGER NOT NULL
    )");
    if ($mysql) {
        if (!$pdo->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_customer_phone'")->fetch()) {
            $pdo->exec('CREATE INDEX idx_orders_customer_phone ON orders (customer_phone)');
        }
        if (!$pdo->query("SHOW INDEX FROM order_lookup_attempts WHERE Key_name = 'idx_order_lookup_ip_time'")->fetch()) {
            $pdo->exec('CREATE INDEX idx_order_lookup_ip_time ON order_lookup_attempts (ip_hash, attempted_at)');
        }
    } else {
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_customer_phone ON orders (customer_phone)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_order_lookup_ip_time ON order_lookup_attempts (ip_hash, attempted_at)');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id $id,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at VARCHAR(32) NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id $id,
        ip_hash VARCHAR(64) NOT NULL,
        attempted_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS webhook_events (
        id $id,
        event_id VARCHAR(160) NOT NULL UNIQUE,
        received_at VARCHAR(32) NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_cache (
        cache_key VARCHAR(100) NOT NULL PRIMARY KEY,
        cache_value $text NOT NULL,
        expires_at INTEGER NOT NULL
    )");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM packages')->fetchColumn();
    if ($count === 0) {
        $seed = [
            ['airtel', 1100, '5 Go', '7 jours', 'Internet mobile Airtel'],
            ['airtel', 3100, '15 Go', '30 jours', 'Internet mobile Airtel'],
            ['airtel', 5800, 'Illimitées', '30 jours', 'Internet mobile Airtel'],
            ['moov', 1200, '6 Go', '7 jours', 'Internet mobile Moov'],
            ['moov', 2600, '13 Go', '30 jours', 'Internet mobile Moov'],
            ['moov', 5300, 'Illimitées', '30 jours', 'Internet mobile Moov'],
        ];
        $statement = $pdo->prepare('INSERT INTO packages (`operator`, price, data_volume, validity, description, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
        foreach ($seed as $package) {
            $statement->execute([...$package, gmdate('Y-m-d H:i:s')]);
        }
    }

    $initialPassword = (string) app_config('ADMIN_INITIAL_PASSWORD', '');
    $admins = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($admins === 0 && strlen($initialPassword) >= 12) {
        $statement = $pdo->prepare('INSERT INTO admin_users (username, password_hash, created_at) VALUES (?, ?, ?)');
        $statement->execute(['admin', password_hash($initialPassword, PASSWORD_DEFAULT), gmdate('Y-m-d H:i:s')]);
    }
}

