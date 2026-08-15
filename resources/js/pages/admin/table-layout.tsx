import { Head, Link, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';

type TableSlotItem = {
    id: number;
    code: string;
    number: number;
    status: 'AVAILABLE' | 'RESERVED' | 'ASSIGNED';
    booking_id: number | null;
    booking_number: string | null;
    customer_name: string | null;
    is_internal_company: boolean;
    is_temporarily_closed: boolean;
};

type RowItem = {
    row_code: string;
    slots: TableSlotItem[];
};

type Props = {
    rows: RowItem[];
    show_closed_slots: boolean;
    background_label: string;
};

function slotTone(status: TableSlotItem['status']): string {
    if (status === 'ASSIGNED') {
        return 'bg-[#1796C7] text-white border-sky-700';
    }

    if (status === 'RESERVED') {
        return 'bg-yellow-300 text-yellow-950 border-yellow-400';
    }

    return 'bg-white text-slate-900 border-slate-300';
}

function slotClass(slot: TableSlotItem, showClosedSlots: boolean): string {
    if (slot.is_internal_company) {
        return 'bg-orange-400 text-orange-950 border-orange-500';
    }

    if (slot.is_temporarily_closed) {
        return showClosedSlots
            ? 'bg-slate-500 text-white border-slate-600'
            : 'invisible';
    }

    return slotTone(slot.status);
}

function slotTitle(slot: TableSlotItem): string {
    if (slot.is_internal_company) {
        return `${slot.code}: Internal Perusahaan`;
    }

    if (slot.is_temporarily_closed) {
        return `${slot.code}: ditutup sementara`;
    }

    if (!slot.booking_number) {
        return `${slot.code}: masih kosong`;
    }

    return `${slot.code} | ${slot.booking_number}${slot.customer_name ? ` | ${slot.customer_name}` : ''}`;
}

export default function AdminTableLayoutPage() {
    const { rows, show_closed_slots, background_label } =
        usePage<Props>().props;
    const leftRows = rows.filter((row) =>
        ['J', 'H', 'G', 'F'].includes(row.row_code),
    );
    const rightRows = rows.filter((row) =>
        ['A', 'B', 'D', 'E'].includes(row.row_code),
    );

    return (
        <>
            <Head title="Layout meja" />

            <main className="table-layout-print-page min-h-screen px-4 py-8 sm:px-6">
                <div className="table-layout-print-content mx-auto max-w-7xl space-y-6">
                    <div className="table-layout-screen-only flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold">
                                Layout meja
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                Lihat meja yang masih kosong, sedang masuk
                                booking, atau sudah disetujui.
                            </p>
                        </div>

                        <div className="flex flex-wrap justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => window.print()}
                                className="inline-flex min-h-11 items-center gap-2 rounded-full bg-[var(--color-brand)] px-5 py-2 text-sm font-semibold text-white"
                            >
                                <Printer aria-hidden="true" size={18} />
                                Cetak denah
                            </button>
                            <Link
                                href="/admin"
                                className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                            >
                                Kembali
                            </Link>
                        </div>
                    </div>

                    <div className="table-layout-print-heading hidden text-center">
                        <h1 className="text-lg font-semibold">Layout meja</h1>
                    </div>

                    <section className="table-layout-legend rounded-[24px] border border-[var(--color-border)] bg-white/90 p-5 shadow-sm sm:p-6">
                        <div className="flex flex-wrap gap-3 text-sm text-slate-700">
                            <div className="flex items-center gap-2">
                                <span className="h-4 w-4 rounded border border-slate-300 bg-white" />
                                <span>Kosong</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="h-4 w-4 rounded border border-yellow-400 bg-yellow-300" />
                                <span>Sudah masuk booking</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="h-4 w-4 rounded border border-sky-700 bg-[#1796C7]" />
                                <span>Sudah disetujui</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="h-4 w-4 rounded border border-orange-500 bg-orange-400" />
                                <span>Internal Perusahaan</span>
                            </div>
                            {show_closed_slots ? (
                                <div className="flex items-center gap-2">
                                    <span className="h-4 w-4 rounded border border-slate-600 bg-slate-500" />
                                    <span>Ditutup sementara</span>
                                </div>
                            ) : null}
                        </div>
                    </section>

                    <section
                        data-testid="table-layout-sheet"
                        className="table-layout-sheet overflow-x-auto rounded-[24px] border border-[var(--color-border)] bg-white/90 p-5 shadow-sm sm:p-6"
                    >
                        <div className="table-layout-canvas mx-auto max-w-[1120px] min-w-[900px] space-y-8">
                            <div className="table-layout-landmark mx-auto flex h-16 w-[220px] items-center justify-center rounded-md border border-slate-500 bg-slate-200 text-sm font-semibold text-slate-800">
                                {background_label}
                            </div>

                            <div className="table-layout-columns flex items-start justify-center gap-12">
                                <div className="table-layout-row-grid grid grid-cols-4 gap-6">
                                    {leftRows.map((row) => (
                                        <div
                                            key={row.row_code}
                                            className="table-layout-row space-y-3"
                                        >
                                            <div className="table-layout-slot-list grid gap-1">
                                                {row.slots.map((slot) =>
                                                    slot.booking_id ? (
                                                        <Link
                                                            key={slot.id}
                                                            href={`/admin/booking/${slot.booking_id}`}
                                                            title={slotTitle(
                                                                slot,
                                                            )}
                                                            className={`table-layout-slot flex h-8 w-14 items-center justify-center rounded border text-xs font-medium transition hover:scale-[1.02] ${slotClass(slot, show_closed_slots)}`}
                                                        >
                                                            {slot.number}
                                                        </Link>
                                                    ) : (
                                                        <div
                                                            key={slot.id}
                                                            title={slotTitle(
                                                                slot,
                                                            )}
                                                            className={`table-layout-slot flex h-8 w-14 items-center justify-center rounded border text-xs font-medium ${slotClass(slot, show_closed_slots)}`}
                                                        >
                                                            {slot.number}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                            {['E', 'J'].includes(
                                                row.row_code,
                                            ) ? (
                                                <div
                                                    aria-hidden="true"
                                                    className="table-layout-row-label h-6"
                                                />
                                            ) : (
                                                <div className="table-layout-row-label rounded bg-[#FD9FC9] px-3 py-1 text-center text-xs font-semibold text-slate-900">
                                                    Row {row.row_code}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>

                                <div className="table-layout-center-aisle w-16 shrink-0" />

                                <div className="table-layout-row-grid grid grid-cols-4 gap-6">
                                    {rightRows.map((row) => (
                                        <div
                                            key={row.row_code}
                                            className="table-layout-row space-y-3"
                                        >
                                            <div className="table-layout-slot-list grid gap-1">
                                                {row.slots.map((slot) =>
                                                    slot.booking_id ? (
                                                        <Link
                                                            key={slot.id}
                                                            href={`/admin/booking/${slot.booking_id}`}
                                                            title={slotTitle(
                                                                slot,
                                                            )}
                                                            className={`table-layout-slot flex h-8 w-14 items-center justify-center rounded border text-xs font-medium transition hover:scale-[1.02] ${slotClass(slot, show_closed_slots)}`}
                                                        >
                                                            {slot.number}
                                                        </Link>
                                                    ) : (
                                                        <div
                                                            key={slot.id}
                                                            title={slotTitle(
                                                                slot,
                                                            )}
                                                            className={`table-layout-slot flex h-8 w-14 items-center justify-center rounded border text-xs font-medium ${slotClass(slot, show_closed_slots)}`}
                                                        >
                                                            {slot.number}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                            {['E', 'J'].includes(
                                                row.row_code,
                                            ) ? (
                                                <div
                                                    aria-hidden="true"
                                                    className="table-layout-row-label h-6"
                                                />
                                            ) : (
                                                <div className="table-layout-row-label rounded bg-[#FD9FC9] px-3 py-1 text-center text-xs font-semibold text-slate-900">
                                                    Row {row.row_code}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="table-layout-landmark mx-auto flex h-20 w-[220px] items-center justify-center rounded-md border border-sky-500 bg-sky-200 text-sm font-semibold text-slate-800">
                                Altar
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
