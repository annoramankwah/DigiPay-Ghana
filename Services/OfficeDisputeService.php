<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DisputeRepository;

class OfficeDisputeService
{
    private const STATUSES = ['under_review', 'resolved', 'rejected'];

    private DisputeRepository $disputes;
    private AuditLogger $audit;
    private NotificationService $notifications;

    public function __construct()
    {
        $this->disputes = new DisputeRepository();
        $this->audit = new AuditLogger();
        $this->notifications = new NotificationService();
    }

    public function listAll(?string $status = null, ?int $institutionId = null): array
    {
        return $this->disputes->findAllForOffice($status, $institutionId);
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function respond(int $officerId, int $disputeId, string $status, ?string $notes, ?int $institutionId = null): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            return ['ok' => false, 'message' => 'Invalid status.'];
        }

        $dispute = $this->disputes->findByIdForOffice($disputeId, $institutionId);
        if (!$dispute) {
            return ['ok' => false, 'message' => 'Dispute not found.'];
        }

        $this->disputes->resolve($disputeId, $officerId, $status, $notes);

        $this->notifications->notify(
            (int) $dispute['institution_id'],
            (int) $dispute['raised_by'],
            sprintf(
                'Update on your dispute for TXN-%d: %s.%s',
                $dispute['transaction_id'],
                ucwords(str_replace('_', ' ', $status)),
                $notes ? ' ' . $notes : ''
            ),
            $status === 'rejected' ? 'error' : ($status === 'resolved' ? 'success' : 'pending')
        );

        $this->audit->log($officerId, 'dispute_responded', 'success', 'dispute', (string) $disputeId, null, [
            'status' => $status,
            'notes' => $notes,
        ]);

        return ['ok' => true];
    }
}
