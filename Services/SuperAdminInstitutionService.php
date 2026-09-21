<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InstitutionRepository;

class SuperAdminInstitutionService
{
    private InstitutionRepository $institutions;
    private AuditLogger $audit;

    public function __construct()
    {
        $this->institutions = new InstitutionRepository();
        $this->audit = new AuditLogger();
    }

    public function list(): array
    {
        return $this->institutions->findAll();
    }

    /**
     * @param array{short_name?:string,email?:string,phone?:string,address?:string,website?:string} $details
     * @return array{ok: bool, message?: string, institution_id?: int}
     */
    public function create(int $actorId, string $name, array $details = []): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'School name is required.'];
        }
        if ($this->institutions->isNameTaken($name)) {
            return ['ok' => false, 'message' => 'A school with that name already exists.'];
        }
        if (!empty($details['email']) && !filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid contact email address.'];
        }

        $institutionId = $this->institutions->create($name, $this->normalizeDetails($details));
        $this->audit->log($actorId, 'institution_created', 'success', 'institution', (string) $institutionId, null, [
            'name' => $name,
        ]);

        return ['ok' => true, 'institution_id' => $institutionId];
    }

    /**
     * @param array{short_name?:string,email?:string,phone?:string,address?:string,website?:string} $details
     * @return array{ok: bool, message?: string}
     */
    public function update(int $actorId, int $institutionId, string $name, array $details = []): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'School name is required.'];
        }
        $existing = $this->institutions->findById($institutionId);
        if (!$existing) {
            return ['ok' => false, 'message' => 'School not found.'];
        }
        if ($this->institutions->isNameTaken($name, $institutionId)) {
            return ['ok' => false, 'message' => 'A school with that name already exists.'];
        }
        if (!empty($details['email']) && !filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid contact email address.'];
        }

        $this->institutions->update($institutionId, $name, $this->normalizeDetails($details));
        $this->audit->log($actorId, 'institution_updated', 'success', 'institution', (string) $institutionId, [
            'name' => $existing['name'],
        ], ['name' => $name]);

        return ['ok' => true];
    }

    /**
     * @param array{short_name?:string,email?:string,phone?:string,address?:string,website?:string} $details
     * @return array{short_name:?string,email:?string,phone:?string,address:?string,website:?string}
     */
    private function normalizeDetails(array $details): array
    {
        $clean = static fn (?string $v): ?string => $v !== null && trim($v) !== '' ? trim($v) : null;
        return [
            'short_name' => $clean($details['short_name'] ?? null),
            'email' => $clean($details['email'] ?? null),
            'phone' => $clean($details['phone'] ?? null),
            'address' => $clean($details['address'] ?? null),
            'website' => $clean($details['website'] ?? null),
        ];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function setStatus(int $actorId, int $institutionId, string $status): array
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return ['ok' => false, 'message' => 'Invalid status.'];
        }
        if (!$this->institutions->findById($institutionId)) {
            return ['ok' => false, 'message' => 'School not found.'];
        }

        $this->institutions->setStatus($institutionId, $status);
        $this->audit->log($actorId, 'institution_status_changed', 'success', 'institution', (string) $institutionId, null, [
            'status' => $status,
        ]);

        return ['ok' => true];
    }
}
