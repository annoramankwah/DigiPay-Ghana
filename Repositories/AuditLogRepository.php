<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class AuditLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function insert(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs
                (institution_id, user_id, action, target_entity, target_id, ip_address, user_agent, before_value, after_value, status)
             VALUES
                (:institution_id, :user_id, :action, :target_entity, :target_id, :ip_address, :user_agent, :before_value, :after_value, :status)'
        );
        $stmt->execute([
            ':institution_id' => $data['institution_id'] ?? null,
            ':user_id' => $data['user_id'] ?? null,
            ':action' => $data['action'],
            ':target_entity' => $data['target_entity'] ?? null,
            ':target_id' => $data['target_id'] ?? null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
            ':before_value' => isset($data['before_value']) ? json_encode($data['before_value']) : null,
            ':after_value' => isset($data['after_value']) ? json_encode($data['after_value']) : null,
            ':status' => $data['status'],
        ]);
    }

    /**
     * Reconciliation remarks (and any other logged action) recorded against
     * one transaction, oldest first, with the actor's name for display.
     */
    public function findByTargetTransaction(int $transactionId, string $action): array
    {
        $stmt = $this->db->prepare(
            "SELECT al.*, u.name AS actor_name
             FROM audit_logs al
             LEFT JOIN users u ON u.user_id = al.user_id
             WHERE al.target_entity = 'transaction' AND al.target_id = ? AND al.action = ?
             ORDER BY al.timestamp ASC"
        );
        $stmt->execute([(string) $transactionId, $action]);
        return $stmt->fetchAll();
    }

    /**
     * Filterable feed for the admin audit log viewer.
     *
     * @param array{action?:string,status?:string,search?:string} $filters
     * @param int|null $institutionId Scope to one school, or null for the platform-wide view (super_admin only).
     */
    public function findAll(array $filters = [], int $limit = 100, ?int $institutionId = null): array
    {
        $sql = 'SELECT al.*, u.name AS actor_name, u.login_id AS actor_login_id
                FROM audit_logs al
                LEFT JOIN users u ON u.user_id = al.user_id
                WHERE 1=1';
        $params = [];

        if ($institutionId !== null) {
            $sql .= ' AND al.institution_id = ?';
            $params[] = $institutionId;
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND al.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (al.action LIKE ? OR u.name LIKE ? OR u.login_id LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY al.timestamp DESC LIMIT ' . max(1, $limit);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
