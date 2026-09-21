<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Support\InstitutionContext;

class AuditLogController
{
    public function index(): void
    {
        $filters = array_filter([
            'status' => (string) Request::input('status', ''),
            'search' => trim((string) Request::input('search', '')),
        ]);

        $logs = (new AuditLogRepository())->findAll($filters, 200, InstitutionContext::id());

        Response::view('admin/audit_log', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }
}
