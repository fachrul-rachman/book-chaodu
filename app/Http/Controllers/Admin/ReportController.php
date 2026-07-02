<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, AdminReportService $reportService): Response
    {
        $validated = $request->validate([
            'tab' => ['nullable', 'in:checkin,finance,agent'],
            'date_field' => ['nullable', 'in:booking,approval'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'package_code' => ['nullable', 'in:PRAYER,INCENSE,COMBO'],
            'sort' => ['nullable', 'in:table_number,incense_number,customer_name,booking_number'],
            'agent_search' => ['nullable', 'string', 'max:120'],
        ]);

        $filters = $reportService->filters($validated);

        return Inertia::render('admin/reports/index', [
            'filters' => $filters,
            'tabs' => [
                ['value' => 'checkin', 'label' => 'Check-in'],
                ['value' => 'finance', 'label' => 'Keuangan'],
                ['value' => 'agent', 'label' => 'Agent'],
            ],
            'sort_options' => [
                ['value' => 'table_number', 'label' => 'Nomor meja'],
                ['value' => 'incense_number', 'label' => 'Nomor hio'],
                ['value' => 'customer_name', 'label' => 'Nama customer'],
                ['value' => 'booking_number', 'label' => 'Nomor booking'],
            ],
            'package_options' => $reportService->packageOptions(),
            'checkin' => $reportService->checkIn($filters),
            'finance' => $reportService->finance($filters),
            'agent' => $reportService->agent($filters),
            'export_urls' => [
                'checkin' => [
                    'xlsx' => route('admin.reports.export', ['tab' => 'checkin', 'format' => 'xlsx']),
                    'pdf' => route('admin.reports.export', ['tab' => 'checkin', 'format' => 'pdf']),
                ],
                'finance' => [
                    'xlsx' => route('admin.reports.export', ['tab' => 'finance', 'format' => 'xlsx']),
                    'pdf' => route('admin.reports.export', ['tab' => 'finance', 'format' => 'pdf']),
                ],
                'agent' => [
                    'xlsx' => route('admin.reports.export', ['tab' => 'agent', 'format' => 'xlsx']),
                    'pdf' => route('admin.reports.export', ['tab' => 'agent', 'format' => 'pdf']),
                ],
            ],
        ]);
    }
}
