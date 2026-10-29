
<?php
/**
 * Database connection and query helpers.
 * Uses PDO, prepared statements, and verified TLS for remote databases.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Build the database connection string.
    $useCustomDsn = defined('DB_DSN');

    if ($useCustomDsn) {
        $dsn = DB_DSN;
    } else {
        $port = defined('DB_PORT') ? DB_PORT : 3306;

        $dsn = 'mysql:host=' . DB_HOST
            . ';port=' . $port
            . ';dbname=' . DB_NAME
            . ';charset=' . DB_CHARSET;
    }

    // Keep the existing PDO behavior.
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Require TLS certificate verification for remote databases.
    $host = strtolower(trim((string) DB_HOST));

    $isRemoteDatabase = !in_array(
        $host,
        ['127.0.0.1', 'localhost', '::1'],
        true
    );

    if ($isRemoteDatabase) {
        if (
            !extension_loaded('pdo_mysql') ||
            !defined('PDO::MYSQL_ATTR_SSL_CA') ||
            !defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')
        ) {
            error_log('PDO MySQL SSL support is unavailable.');
            http_response_code(500);
            exit('Secure database connection is unavailable.');
        }

        // Default location: library/includes/ca.pem.
        $caPath = getenv('DB_SSL_CA_PATH');

        if ($caPath === false || $caPath === '') {
            $caPath = __DIR__ . '/ca.pem';
        }

        if (!is_file($caPath) || !is_readable($caPath)) {
            error_log('Database CA certificate is missing or unreadable.');
            http_response_code(500);
            exit('Secure database connection is not configured.');
        }

        $options[constant('PDO::MYSQL_ATTR_SSL_CA')] = $caPath;

        $options[
            constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')
        ] = true;
    }

    try {
        $pdo = new PDO(
            $dsn,
            $useCustomDsn ? null : DB_USER,
            $useCustomDsn ? null : DB_PASS,
            $options
        );
    } catch (PDOException $e) {
        // Keep database details in server logs, not public responses.
        error_log('Database connection failed: ' . $e->getMessage());

        http_response_code(500);
        exit('Cannot connect to the database. Please try again later.');
    }

    return $pdo;
}

/**
 * Fetch multiple rows.
 */
function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);

    return $st->fetchAll();
}

/**
 * Fetch one row, or null if no row exists.
 */
function db_one(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);

    $row = $st->fetch();

    return $row === false ? null : $row;
}

/**
 * Fetch one column, or null if no value exists.
 */
function db_value(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);

    $value = $st->fetchColumn();

    return $value === false ? null : $value;
}

/**
 * Run INSERT, UPDATE, or DELETE.
 * Returns the number of affected rows.
 */
function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);

    return $st->rowCount();
}
