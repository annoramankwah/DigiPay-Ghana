<?php

declare(strict_types=1);

namespace App\Controllers\Office;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\OfficeDisputeService;
use App\Support\InstitutionContext;

class DisputeController
{
    public function index(): void
    {
        $status = (string) Request::input('status', '');
        $disputes = (new OfficeDisputeService())->listAll($status !== '' ? $status : null, InstitutionContext::id());

        Response::view('office/disputes', [
            'disputes' => $disputes,
            'status' => $status,
            'error' => Session::flash('office_dispute_error'),
            'csrf' => Csrf::token(),
        ]);
    }

    public function respond(array $params): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/office/disputes');
        }

        $disputeId = (int) $params['disputeId'];
        $status = (string) Request::input('status', '');
        $notes = trim((string) Request::input('notes', ''));

        $result = (new OfficeDisputeService())->respond(
            (int) Session::get('user_id'),
            $disputeId,
            $status,
            $notes !== '' ? $notes : null,
            InstitutionContext::id()
        );

        if (!$result['ok']) {
            Session::flash('office_dispute_error', $result['message']);
        }

        Response::redirect('/office/disputes');
    }
}
