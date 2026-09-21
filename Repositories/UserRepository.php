<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use DateTimeImmutable;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByLoginId(string $loginId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE login_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$loginId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmailAndLoginId(string $loginId, string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE login_id = ? AND email = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$loginId, $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByResetTokenHash(string $hash): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE reset_token_hash = ? LIMIT 1');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function incrementFailedAttempts(int $userId): int
    {
        $this->db->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE user_id = ?')
            ->execute([$userId]);
        $stmt = $this->db->prepare('SELECT failed_login_attempts FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function lockAccount(int $userId, DateTimeImmutable $until): void
    {
        $stmt = $this->db->prepare('UPDATE users SET locked_until = ?, failed_login_attempts = 0 WHERE user_id = ?');
        $stmt->execute([$until->format('Y-m-d H:i:s'), $userId]);
    }

    public function resetFailedAttempts(int $userId): void
    {
        $this->db->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = ?')
            ->execute([$userId]);
    }

    public function setResetToken(int $userId, string $tokenHash, DateTimeImmutable $expiresAt): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE user_id = ?'
        );
        $stmt->execute([$tokenHash, $expiresAt->format('Y-m-d H:i:s'), $userId]);
    }

    public function isEmailTakenByOther(string $email, int $exceptUserId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ? AND deleted_at IS NULL');
        $stmt->execute([$email, $exceptUserId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function updateProfile(int $userId, string $name, string $email): void
    {
        $stmt = $this->db->prepare('UPDATE users SET name = ?, email = ? WHERE user_id = ?');
        $stmt->execute([$name, $email, $userId]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        // A completed reset proves identity via the emailed token, so it also
        // clears any lockout — otherwise a locked-out user who resets their
        // password correctly still cannot sign in until the lock expires.
        $stmt = $this->db->prepare(
            'UPDATE users SET
                password_hash = ?,
                reset_token_hash = NULL,
                reset_token_expires_at = NULL,
                failed_login_attempts = 0,
                locked_until = NULL
             WHERE user_id = ?'
        );
        $stmt->execute([$passwordHash, $userId]);
    }

    public function isLoginIdTaken(string $loginId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE login_id = ?');
        $stmt->execute([$loginId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function isEmailTaken(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND deleted_at IS NULL');
        $stmt->execute([$email]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Admin-facing user list, including soft-deleted accounts (shown as
     * "Deleted" rather than hidden, so the audit trail stays legible), with
     * program/level attached for student rows.
     *
     * @param array{role?:string,status?:string,search?:string} $filters
     * @param int|null $institutionId Scope to one school, or null for the platform-wide view (super_admin only).
     */
    public function findAllForAdmin(array $filters = [], ?int $institutionId = null): array
    {
        $sql = "SELECT u.*, s.program, s.level
                FROM users u
                LEFT JOIN students s ON s.user_id = u.user_id
                WHERE 1=1";
        $params = [];

        if ($institutionId !== null) {
            $sql .= ' AND u.institution_id = ?';
            $params[] = $institutionId;
        }
        if (!empty($filters['role'])) {
            $sql .= ' AND u.role = ?';
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'deleted') {
                $sql .= ' AND u.deleted_at IS NOT NULL';
            } else {
                $sql .= ' AND u.status = ? AND u.deleted_at IS NULL';
                $params[] = $filters['status'];
            }
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (u.name LIKE ? OR u.login_id LIKE ? OR u.email LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY u.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByIdIncludingDeleted(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, s.student_id, s.program, s.level, s.contact_info
             FROM users u
             LEFT JOIN students s ON s.user_id = u.user_id
             WHERE u.user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (login_id, name, email, role, institution_id, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['login_id'],
            $data['name'],
            $data['email'],
            $data['role'],
            $data['institution_id'] ?? null,
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateRole(int $userId, string $role): void
    {
        $this->db->prepare('UPDATE users SET role = ? WHERE user_id = ?')->execute([$role, $userId]);
    }

    public function updateInstitution(int $userId, ?int $institutionId): void
    {
        $this->db->prepare('UPDATE users SET institution_id = ? WHERE user_id = ?')->execute([$institutionId, $userId]);
    }

    public function setStatus(int $userId, string $status): void
    {
        $this->db->prepare('UPDATE users SET status = ? WHERE user_id = ?')->execute([$status, $userId]);
    }

    public function softDelete(int $userId): void
    {
        $this->db->prepare('UPDATE users SET deleted_at = NOW(), status = ? WHERE user_id = ?')
            ->execute(['inactive', $userId]);
    }

    public function restore(int $userId): void
    {
        $this->db->prepare("UPDATE users SET deleted_at = NULL, status = 'active' WHERE user_id = ?")
            ->execute([$userId]);
    }

    public function countActiveByRole(?int $institutionId = null): array
    {
        $sql = "SELECT role, COUNT(*) AS n FROM users WHERE status = 'active' AND deleted_at IS NULL";
        $params = [];
        if ($institutionId !== null) {
            $sql .= ' AND institution_id = ?';
            $params[] = $institutionId;
        }
        $sql .= ' GROUP BY role';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $counts = ['student' => 0, 'account_office' => 0, 'admin' => 0, 'super_admin' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['role']] = (int) $row['n'];
        }
        return $counts;
    }
}
