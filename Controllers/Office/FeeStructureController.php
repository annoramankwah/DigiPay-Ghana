<?php

declare(strict_types=1);

namespace App\Controllers\Office;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\FeeStructureRepository;
use App\Services\FeeStructureService;
use App\Support\InstitutionContext;

class FeeStructureController
{
    public function index(): void
    {
        $institutionId = InstitutionContext::id();
        $feeItems = $institutionId !== null ? (new FeeStructureRepository())->findAllCurrent($institutionId) : [];

        $grouped = [];
        foreach ($feeItems as $item) {
            $key = $item['program'] . '|' . $item['level'] . '|' . $item['academic_term'];
            $grouped[$key]['program'] = $item['program'];
            $grouped[$key]['level'] = $item['level'];
            $grouped[$key]['academic_term'] = $item['academic_term'];
            $grouped[$key]['effective_from'] = $item['effective_from'];
            $grouped[$key]['items'][] = $item;
        }

        Response::view('office/fee_structures', [
            'groups' => array_values($grouped),
            'error' => Session::flash('fee_error'),
            'saved' => Session::flash('fee_saved') === '1',
            'csrf' => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/office/fee-structures');
        }

        $institutionId = InstitutionContext::id();
        if ($institutionId === null) {
            Response::redirect('/office/fee-structures');
        }

        $result = (new FeeStructureService())->create((int) Session::get('user_id'), [
            'institution_id' => $institutionId,
            'program' => trim((string) Request::input('program', '')),
            'level' => trim((string) Request::input('level', '')),
            'academic_term' => trim((string) Request::input('academic_term', '')),
            'category' => trim((string) Request::input('category', '')),
            'amount' => (string) Request::input('amount', ''),
            'due_date' => (string) Request::input('due_date', ''),
            'effective_from' => (string) Request::input('effective_from', date('Y-m-d')),
        ]);

        if (!$result['ok']) {
            Session::flash('fee_error', $result['message']);
        } else {
            Session::flash('fee_saved', '1');
        }

        Response::redirect('/office/fee-structures');
    }

    public function revise(array $params): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/office/fee-structures');
        }

        $feeId = (int) $params['feeId'];
        $amount = (float) Request::input('amount', 0);
        $dueDate = (string) Request::input('due_date', '');

        $result = (new FeeStructureService())->revise((int) Session::get('user_id'), $feeId, $amount, $dueDate, InstitutionContext::id());

        if (!$result['ok']) {
            Session::flash('fee_error', $result['message']);
        } else {
            Session::flash('fee_saved', '1');
        }

        Response::redirect('/office/fee-structures');
    }
}
