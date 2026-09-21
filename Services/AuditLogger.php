<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\AuditLogRepository;
use App\Support\InstitutionContext;

class AuditLogger
{
    private AuditLogRepository $repo;

    public function __construct()
    {
        $this->repo = new AuditLogRepository();
    }

    public function log(
        ?int $userId,
        string $action,
        string $status,
        ?string $targetEntity = null,
        ?string $targetId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        $this->repo->insert([
            'institution_id' => InstitutionContext::id(),
            'user_id' => $userId,
            'action' => $action,
            'status' => $status,
            'target_entity' => $targetEntity,
            'target_id' => $targetId,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'before_value' => $before,
            'after_value' => $after,
        ]);
    }
}
