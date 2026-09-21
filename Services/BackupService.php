<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;

/**
 * FR-ADM-07 (Could-priority). Deliberately minimal: a PHP-level export of
 * every table's rows to a timestamped JSON file, never a shell-out to
 * mysqldump — running an external command from a web request is its own
 * security surface and isn't worth it for a "Could" feature. Restore is
 * intentionally NOT implemented: blindly overwriting live data from an
 * uploaded/selected file is a destructive action disproportionate to this
 * feature's priority, so the UI surfaces backup creation and history only.
 */
class BackupService
{
    private const TABLES = [
        'institutions', 'users', 'students', 'fee_structures', 'transactions',
        'payment_attempts', 'receipts', 'disputes', 'audit_logs', 'notifications',
    ];

    private string $dir;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->dir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        $this->audit = new AuditLogger();
    }

    public function create(int $adminId): array
    {
        $db = Database::connection();
        $dump = ['created_at' => date('c'), 'tables' => []];
        $recordCount = 0;

        foreach (self::TABLES as $table) {
            $rows = $db->query('SELECT * FROM ' . $table)->fetchAll();
            // Password hashes have no business leaving the database, even in
            // an admin-only backup file sitting on disk.
            if ($table === 'users') {
                foreach ($rows as &$row) {
                    unset($row['password_hash'], $row['reset_token_hash']);
                }
                unset($row);
            }
            $dump['tables'][$table] = $rows;
            $recordCount += count($rows);
        }

        $filename = 'backup-' . date('Y-m-d_His') . '.json';
        $path = $this->dir . '/' . $filename;
        file_put_contents($path, json_encode($dump, JSON_PRETTY_PRINT));

        $this->audit->log($adminId, 'backup_created', 'success', 'system', $filename, null, [
            'records' => $recordCount,
            'size_bytes' => filesize($path),
        ]);

        return ['filename' => $filename, 'records' => $recordCount, 'size_bytes' => filesize($path)];
    }

    /**
     * @return array<int, array{filename:string, size_bytes:int, created_at:string}>
     */
    public function history(): array
    {
        $files = glob($this->dir . '/*.json') ?: [];
        rsort($files);

        return array_map(static function (string $path) {
            return [
                'filename' => basename($path),
                'size_bytes' => filesize($path),
                'created_at' => date('Y-m-d H:i:s', filemtime($path)),
            ];
        }, $files);
    }
}
