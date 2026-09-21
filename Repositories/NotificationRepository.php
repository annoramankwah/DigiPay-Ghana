<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class NotificationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(int $institutionId, int $userId, string $message, string $type, string $channel = 'in_app'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (institution_id, user_id, message, type, channel, read_status)
             VALUES (?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([$institutionId, $userId, $message, $type, $channel]);
        return (int) $this->db->lastInsertId();
    }

    public function findByUserId(int $userId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY timestamp DESC LIMIT ' . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markAllRead(int $userId): void
    {
        $this->db->prepare('UPDATE notifications SET read_status = 1 WHERE user_id = ?')->execute([$userId]);
    }
}
