<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TableSlot;
use Inertia\Inertia;
use Inertia\Response;

class TableLayoutController extends Controller
{
    public function __invoke(): Response
    {
        $rowOrder = ['J', 'H', 'G', 'F', 'A', 'B', 'D', 'E'];
        $slots = TableSlot::query()
            ->with(['booking:id,booking_number,customer_name'])
            ->orderBy('number', 'desc')
            ->orderBy('allocation_order')
            ->get()
            ->groupBy('row_code');

        return Inertia::render('admin/table-layout', [
            'rows' => collect($rowOrder)
                ->map(fn (string $rowCode): array => [
                    'row_code' => $rowCode,
                    'slots' => collect($slots->get($rowCode, []))
                        ->sortByDesc('number')
                        ->values()
                        ->map(fn (TableSlot $slot): array => [
                            'id' => $slot->id,
                            'code' => $slot->code,
                            'number' => $slot->number,
                            'status' => $slot->status->value,
                            'booking_id' => $slot->booking_id,
                            'booking_number' => $slot->booking?->booking_number,
                            'customer_name' => $slot->booking?->customer_name,
                        ])
                        ->all(),
                ])
                ->all(),
        ]);
    }
}
