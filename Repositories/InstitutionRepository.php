<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class InstitutionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findById(int $institutionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM institutions WHERE institution_id = ? LIMIT 1');
        $stmt->execute([$institutionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array> */
    public function findAll(): array
    {
        return $this->db->query('SELECT * FROM institutions ORDER BY name ASC')->fetchAll();
    }

    public function isNameTaken(string $name, ?int $exceptInstitutionId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM institutions WHERE name = ?';
        $params = [$name];
        if ($exceptInstitutionId !== null) {
            $sql .= ' AND institution_id <> ?';
            $params[] = $exceptInstitutionId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * @param array{short_name?:?string,email?:?string,phone?:?string,address?:?string,website?:?string} $details
     */
    public function create(string $name, array $details = []): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO institutions (name, short_name, email, phone, address, website, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $details['short_name'] ?? null,
            $details['email'] ?? null,
            $details['phone'] ?? null,
            $details['address'] ?? null,
            $details['website'] ?? null,
            'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array{short_name?:?string,email?:?string,phone?:?string,address?:?string,website?:?string} $details
     */
    public function update(int $institutionId, string $name, array $details = []): void
    {
        $stmt = $this->db->prepare(
            'UPDATE institutions SET name = ?, short_name = ?, email = ?, phone = ?, address = ?, website = ? WHERE institution_id = ?'
        );
        $stmt->execute([
            $name,
            $details['short_name'] ?? null,
            $details['email'] ?? null,
            $details['phone'] ?? null,
            $details['address'] ?? null,
            $details['website'] ?? null,
            $institutionId,
        ]);
    }

    public function setStatus(int $institutionId, string $status): void
    {
        $this->db->prepare('UPDATE institutions SET status = ? WHERE institution_id = ?')
            ->execute([$status, $institutionId]);
    }
}
