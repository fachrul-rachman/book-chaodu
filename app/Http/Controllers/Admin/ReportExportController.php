<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminReportExportService;
use App\Services\AdminReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(
        Request $request,
        string $tab,
        string $format,
        AdminReportService $reportService,
        AdminReportExportService $reportExportService,
    ): BinaryFileResponse|StreamedResponse|Response {
        abort_unless(in_array($tab, ['checkin', 'finance', 'agent'], true), 404);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $validated = $request->validate([
            'date_field' => ['nullable', 'in:booking,approval'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'package_code' => ['nullable', 'in:PRAYER,INCENSE,COMBO'],
            'sort' => ['nullable', 'in:table_number,incense_number,customer_name,booking_number'],
            'agent_search' => ['nullable', 'string', 'max:120'],
        ]);

        $filters = $reportService->filters(array_merge($validated, ['tab' => $tab]));

        if ($format === 'pdf') {
            return $reportExportService->exportPdf($tab, $filters);
        }

        return $reportExportService->exportXlsx($tab, $filters);
    }
}
