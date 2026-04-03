<?php
// ============================================================
// DMR Store Management — Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'dmr_store');
define('DB_USER', 'root');         // Change to your MySQL user
define('DB_PASS', '');             // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
                PDO::ATTR_PERSISTENT         => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$instance->exec("SET time_zone = '+05:30'");
            } catch (PDOException $e) {
                error_log('[DMR-DB] Connection failed: ' . $e->getMessage());
                die(json_encode(['error' => 'Database connection failed. Please check configuration.']));
            }
        }
        return self::$instance;
    }

    // Execute a query and return PDOStatement
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Fetch single row
    public static function fetchOne(string $sql, array $params = []): ?array {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    // Fetch all rows
    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    // Insert and return last insert id
    public static function insert(string $sql, array $params = []): int|string {
        self::query($sql, $params);
        return self::getInstance()->lastInsertId();
    }

    // Transaction helpers
    public static function beginTransaction(): void { self::getInstance()->beginTransaction(); }
    public static function commit(): void           { self::getInstance()->commit(); }
    public static function rollback(): void         { self::getInstance()->rollBack(); }

    // Get a setting value
    public static function getSetting(string $key, string $default = ''): string {
        $row = self::fetchOne("SELECT value FROM settings WHERE key_name = ?", [$key]);
        return $row ? ($row['value'] ?? $default) : $default;
    }

    // Generate next sequential number for GRN/IND/ISS/TXN
    public static function nextNumber(string $prefix_key, string $table, string $column): string {
        $prefix = self::getSetting($prefix_key, strtoupper($prefix_key));
        $year   = date('Y');
        $pattern = "{$prefix}-{$year}-%";
        $row = self::fetchOne(
            "SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1",
            [$pattern]
        );
        $seq = 1;
        if ($row) {
            $parts = explode('-', $row[$column]);
            $seq   = (int)end($parts) + 1;
        }
        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }
}
