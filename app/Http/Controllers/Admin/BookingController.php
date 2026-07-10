<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalIntegrationComponent;
use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use App\Services\AdminBookingUpdateService;
use App\Services\InternalCompanySlotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(
        private readonly InternalCompanySlotService $internalCompanySlotService,
    ) {}

    public function index(Request $request): Response
    {
        $selectedStatus = $this->selectedStatus($request->query('status'));
        $query = Booking::query()
            ->with(['tableSlots', 'incenseSlots'])
            ->where(function ($builder): void {
                $builder
                    ->where(function ($pending): void {
                        $pending->where('status', BookingStatus::Pending)
                            ->whereHas('payment');
                    })
                    ->orWhere('status', BookingStatus::Approved)
                    ->orWhere(function ($rejected): void {
                        $rejected->where('status', BookingStatus::Rejected)
                            ->whereHas('payment');
                    });
            })
            ->latest('id');

        if ($selectedStatus) {
            $query->where('status', $selectedStatus);
        }

        return Inertia::render('admin/bookings/index', [
            'selected_status' => $selectedStatus ? $selectedStatus->value : 'ALL',
            'status_options' => [
                ['value' => 'ALL', 'label' => 'Semua'],
                ['value' => BookingStatus::Pending->value, 'label' => 'Pending'],
                ['value' => BookingStatus::Approved->value, 'label' => 'Approve'],
                ['value' => BookingStatus::Rejected->value, 'label' => 'Reject'],
            ],
            'status_counts' => [
                'ALL' => Booking::query()
                    ->where(function ($builder): void {
                        $builder
                            ->where(function ($pending): void {
                                $pending->where('status', BookingStatus::Pending)
                                    ->whereHas('payment');
                            })
                            ->orWhere('status', BookingStatus::Approved)
                            ->orWhere(function ($rejected): void {
                                $rejected->where('status', BookingStatus::Rejected)
                                    ->whereHas('payment');
                            });
                    })
                    ->count(),
                BookingStatus::Pending->value => Booking::query()->where('status', BookingStatus::Pending)->whereHas('payment')->count(),
                BookingStatus::Approved->value => Booking::query()->where('status', BookingStatus::Approved)->count(),
                BookingStatus::Rejected->value => Booking::query()->where('status', BookingStatus::Rejected)->whereHas('payment')->count(),
            ],
            'bookings' => $query
                ->get()
                ->map(fn (Booking $booking): array => $this->listItem($booking))
                ->values()
                ->all(),
        ]);
    }

    public function show(Booking $booking): Response
    {
        $booking->load(['names', 'meal', 'payment', 'prayerPapers', 'tableSlots', 'incenseSlots']);
        $booking->load('approvalIntegration');

        $deceasedNames = $booking->names
            ->where('category', BookingNameCategory::Deceased)
            ->sortBy('position')
            ->map(fn ($name): array => [
                'position' => $name->position,
                'indonesian_name' => $name->indonesian_name,
                'mandarin_name' => $name->mandarin_name,
            ])
            ->values()
            ->all();

        while (count($deceasedNames) < 2) {
            $deceasedNames[] = [
                'position' => count($deceasedNames) + 1,
                'indonesian_name' => '',
                'mandarin_name' => '',
            ];
        }

        $incenseName = $booking->names
            ->first(fn ($name): bool => $name->category === BookingNameCategory::Incense);
        $meal = $booking->meal;
        $payment = $booking->payment;
        $approvalIntegration = $booking->approvalIntegration;
        $prayerPapers = [];

        foreach ($booking->prayerPapers->sortBy(['type', 'sequence']) as $paper) {
            $prayerPapers[] = [
                'id' => $paper->id,
                'type' => (string) $paper->getRawOriginal('type'),
                'sequence' => (int) $paper->sequence,
                'status' => (string) $paper->getRawOriginal('status'),
                'file_url' => $paper->file_path
                    ? route('admin.prayer-papers.show', $paper)
                    : null,
            ];
        }

        return Inertia::render('admin/bookings/show', [
            'booking' => [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'status' => $booking->status->value,
                'package_name' => $booking->package_name_snapshot,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'customer_email' => $booking->customer_email,
                'attendee_count' => $booking->attendee_count,
                'referral_source' => $booking->referral_source,
                'source_label' => $this->sourceLabel($booking),
                'agent_name' => $booking->agent_name,
                'rejection_reason' => $booking->rejection_reason,
                'proof_path' => $payment?->proof_path,
                'proof_url' => $payment?->proof_path
                    ? route('admin.bookings.proof.show', $booking)
                    : null,
                'virtual_account_bank_name' => $payment?->virtual_account_bank_name,
                'virtual_account_number' => $payment?->virtual_account_number,
                'virtual_account_holder' => $payment?->virtual_account_holder,
                'sender_name' => $payment?->sender_name,
                'transferred_amount' => $payment?->transferred_amount,
                'transfer_date' => optional($payment?->transfer_date)->toDateString(),
                'vegetarian_quantity' => $meal ? $meal->vegetarian_quantity : 0,
                'non_vegetarian_quantity' => $meal ? $meal->non_vegetarian_quantity : 0,
                'deceased_names' => $deceasedNames,
                'incense_name' => [
                    'position' => 1,
                    'indonesian_name' => $incenseName ? $incenseName->indonesian_name : '',
                    'mandarin_name' => $incenseName ? $incenseName->mandarin_name : '',
                ],
                'table_slots' => $booking->tableSlots
                    ->sortBy('allocation_order')
                    ->map(fn ($slot): array => [
                        'id' => $slot->id,
                        'code' => $slot->code,
                        'status' => $slot->status->value,
                    ])
                    ->values()
                    ->all(),
                'incense_slots' => $booking->incenseSlots
                    ->sortBy('allocation_order')
                    ->map(fn ($slot): array => [
                        'id' => $slot->id,
                        'number' => $slot->number,
                        'status' => $slot->status->value,
                    ])
                    ->values()
                    ->all(),
                'prayer_paper_status' => $booking->prayer_paper_status
                    ? $booking->prayer_paper_status->value
                    : null,
                'prayer_papers' => $prayerPapers,
                'approval_integration' => $approvalIntegration ? [
                    'qr_status' => $approvalIntegration->qr_status->value,
                    'qr_error' => $approvalIntegration->qr_error,
                    'qr_url' => $approvalIntegration->qr_image_path
                        ? route('admin.bookings.qr.show', $booking)
                        : null,
                    'drive_status' => $approvalIntegration->drive_status->value,
                    'drive_error' => $approvalIntegration->drive_error,
                    'drive_url' => $approvalIntegration->drive_url,
                    'notion_status' => $approvalIntegration->notion_status->value,
                    'notion_error' => $approvalIntegration->notion_error,
                    'notion_url' => $approvalIntegration->notion_url,
                    'approval_email_status' => $approvalIntegration->approval_email_status->value,
                    'approval_email_error' => $approvalIntegration->approval_email_error,
                    'approval_email_sent_at' => optional($approvalIntegration->approval_email_sent_at)->format('d M Y H:i'),
                    'retry_urls' => [
                        ApprovalIntegrationComponent::Qr->value => route('admin.bookings.integrations.retry', [$booking, ApprovalIntegrationComponent::Qr->value]),
                        ApprovalIntegrationComponent::Drive->value => route('admin.bookings.integrations.retry', [$booking, ApprovalIntegrationComponent::Drive->value]),
                        ApprovalIntegrationComponent::Notion->value => route('admin.bookings.integrations.retry', [$booking, ApprovalIntegrationComponent::Notion->value]),
                        ApprovalIntegrationComponent::ApprovalEmail->value => route('admin.bookings.integrations.retry', [$booking, ApprovalIntegrationComponent::ApprovalEmail->value]),
                    ],
                ] : null,
            ],
            'slot_options' => [
                'tables' => TableSlot::query()
                    ->where(function ($query) use ($booking): void {
                        $query->whereNull('booking_id')
                            ->orWhere('booking_id', $booking->id);
                    })
                    ->whereNotIn('code', $this->internalCompanySlotService->tableCodes())
                    ->orderBy('allocation_order')
                    ->get()
                    ->map(fn (TableSlot $slot): array => [
                        'id' => $slot->id,
                        'code' => $slot->code,
                        'status' => $slot->status->value,
                    ])
                    ->values()
                    ->all(),
                'incense' => IncenseSlot::query()
                    ->where(function ($query) use ($booking): void {
                        $query->whereNull('booking_id')
                            ->orWhere('booking_id', $booking->id);
                    })
                    ->whereNotIn('number', $this->internalCompanySlotService->incenseNumbers())
                    ->orderBy('allocation_order')
                    ->get()
                    ->map(fn (IncenseSlot $slot): array => [
                        'id' => $slot->id,
                        'number' => $slot->number,
                        'status' => $slot->status->value,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function update(
        UpdateBookingRequest $request,
        Booking $booking,
        AdminBookingUpdateService $adminBookingUpdateService,
    ): RedirectResponse {
        $adminBookingUpdateService->update(
            $booking,
            $request->validated(),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'Perubahan booking berhasil disimpan.');
    }

    /**
     * @return array{
     *     id:int,
     *     booking_number:string,
     *     customer_name:string,
     *     customer_phone:string,
     *     package_name:string,
     *     status:string,
     *     table_slot:string|null,
     *     incense_slot:int|null,
     *     created_at:string|null
     * }
     */
    private function listItem(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'package_name' => $booking->package_name_snapshot,
            'status' => $booking->status->value,
            'source_label' => $this->sourceLabel($booking),
            'table_slot' => $booking->tableSlots->sortBy('allocation_order')->first()?->code,
            'incense_slot' => $booking->incenseSlots->sortBy('allocation_order')->first()?->number,
            'created_at' => optional($booking->created_at)->format('d M Y H:i'),
        ];
    }

    private function sourceLabel(Booking $booking): string
    {
        return match ($booking->referral_source) {
            'TEMAN' => 'Teman',
            'KELUARGA' => 'Keluarga',
            'MEDIA_SOSIAL' => 'Media sosial',
            'WEBSITE' => 'Website',
            'AGENT' => 'Agent',
            $this->internalCompanySlotService->sourceValue() => $this->internalCompanySlotService->sourceLabel(),
            default => '-',
        };
    }

    private function selectedStatus(mixed $value): ?BookingStatus
    {
        if (! is_string($value)) {
            return null;
        }

        return match ($value) {
            BookingStatus::Pending->value => BookingStatus::Pending,
            BookingStatus::Approved->value => BookingStatus::Approved,
            BookingStatus::Rejected->value => BookingStatus::Rejected,
            default => null,
        };
    }
}
