<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Database;
use App\Config\Env;
use PDO;

/**
 * Shared by database/migrate.php (CLI) and the PHPUnit bootstrap (which
 * points DB_NAME at a throwaway test database before calling this).
 */
class Migrator
{
    public static function run(bool $silent = false): void
    {
        $dbName = (string) Env::get('DB_NAME', 'digipay_ghana');
        $server = Database::serverConnection();
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS _migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(191) NOT NULL UNIQUE,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $applied = $pdo->query('SELECT filename FROM _migrations')->fetchAll(PDO::FETCH_COLUMN);

        $files = glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            if (!$silent) {
                echo "APPLY {$name}\n";
            }
            $pdo->exec((string) file_get_contents($file));
            $stmt = $pdo->prepare('INSERT INTO _migrations (filename) VALUES (?)');
            $stmt->execute([$name]);
        }
    }

    /**
     * Wipes every row (children first) without dropping tables — used
     * between test cases so each starts from a clean, known state.
     */
    public static function truncateAll(): void
    {
        $pdo = Database::connection();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'notifications', 'audit_logs', 'disputes', 'receipts', 'payment_attempts',
            'transactions', 'fee_structures', 'student_roster', 'students', 'users', 'institutions',
        ] as $table) {
            $pdo->exec('TRUNCATE TABLE ' . $table);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
