<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AdminRosterService;
use App\Support\InstitutionContext;

class RosterController
{
    public function index(): void
    {
        $filters = array_filter([
            'status' => (string) Request::input('status', ''),
            'search' => trim((string) Request::input('search', '')),
        ]);

        $roster = (new AdminRosterService())->list($filters, InstitutionContext::id());

        Response::view('admin/roster', [
            'roster' => $roster,
            'filters' => $filters,
            'error' => Session::flash('roster_error'),
            'saved' => Session::flash('roster_saved'),
            'csrf' => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/roster');
        }

        $institutionId = InstitutionContext::id();
        if ($institutionId === null) {
            Session::flash('roster_error', 'Select a school to manage before adding roster entries.');
            Response::redirect('/admin/roster');
        }
        $result = (new AdminRosterService())->addEntry((int) Session::get('user_id'), $institutionId, [
            'student_number' => Request::input('student_number', ''),
            'full_name' => Request::input('full_name', ''),
            'date_of_birth' => Request::input('date_of_birth', ''),
            'program' => Request::input('program', ''),
            'level' => Request::input('level', ''),
        ]);

        Session::flash('roster_error', $result['ok'] ? null : $result['message']);
        Session::flash('roster_saved', $result['ok'] ? 'Student added to the roster.' : null);
        Response::redirect('/admin/roster');
    }

    public function import(): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/roster');
        }

        $file = $_FILES['csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('roster_error', 'Please choose a CSV file to upload.');
            Response::redirect('/admin/roster');
        }

        $content = file_get_contents($file['tmp_name']);
        $institutionId = InstitutionContext::id();
        if ($institutionId === null) {
            Session::flash('roster_error', 'Select a school to manage before importing a roster.');
            Response::redirect('/admin/roster');
        }
        $result = (new AdminRosterService())->importCsv(
            (int) Session::get('user_id'),
            $institutionId,
            (string) $content
        );

        if (!$result['ok']) {
            Session::flash('roster_error', $result['message']);
        } else {
            $message = $result['message'];
            if (!empty($result['skipped'])) {
                $message .= ' Skipped: ' . implode(' ', $result['skipped']);
            }
            Session::flash('roster_saved', $message);
        }

        Response::redirect('/admin/roster');
    }

    public function delete(array $params): void
    {
        if (!Csrf::verify(Request::input('csrf'))) {
            Response::redirect('/admin/roster');
        }

        $result = (new AdminRosterService())->deleteEntry((int) Session::get('user_id'), (int) $params['rosterId'], InstitutionContext::id());

        Session::flash('roster_error', $result['ok'] ? null : $result['message']);
        Session::flash('roster_saved', $result['ok'] ? 'Removed from roster.' : null);
        Response::redirect('/admin/roster');
    }
}
