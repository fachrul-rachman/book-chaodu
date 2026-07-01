import { Head, Link, usePage } from '@inertiajs/react';

type TableSlotItem = {
    id: number;
    code: string;
    number: number;
    status: 'AVAILABLE' | 'RESERVED' | 'ASSIGNED';
    booking_id: number | null;
    booking_number: string | null;
    customer_name: string | null;
};

type RowItem = {
    row_code: string;
    slots: TableSlotItem[];
};

type Props = {
    rows: RowItem[];
};

function slotTone(status: TableSlotItem['status']): string {
    if (status === 'ASSIGNED') {
        return 'bg-emerald-500 text-emerald-950 border-emerald-600';
    }

    if (status === 'RESERVED') {
        return 'bg-amber-300 text-amber-950 border-amber-400';
    }

    return 'bg-white text-slate-800 border-slate-300';
}

function slotTitle(slot: TableSlotItem): string {
    if (!slot.booking_number) {
        return `${slot.code}: masih kosong`;
    }

    return `${slot.code} | ${slot.booking_number}${slot.customer_name ? ` | ${slot.customer_name}` : ''}`;
}

export default function AdminTableLayoutPage() {
    const { rows } = usePage<Props>().props;
    const leftRows = rows.filter((row) =>
        ['J', 'H', 'G', 'F'].includes(row.row_code),
    );
    const rightRows = rows.filter((row) =>
        ['A', 'B', 'D', 'E'].includes(row.row_code),
    );

    return (
        <>
            <Head title="Layout meja" />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-7xl space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold">
                                Layout meja
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                Lihat meja yang masih kosong, sedang masuk
                                booking, atau sudah disetujui.
                            </p>
                        </div>

                        <Link
                            href="/admin"
                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </div>

                    <section className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-5 shadow-sm sm:p-6">
                        <div className="flex flex-wrap gap-3 text-sm text-slate-700">
                            <div className="flex items-center gap-2">
                                <span className="h-4 w-4 rounded border border-slate-300 bg-white" />
                                <span>Kosong</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="h-4 w-4 rounded border border-amber-400 bg-amber-300" />
                                <span>Sudah masuk booking</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="h-4 w-4 rounded border border-emerald-600 bg-emerald-500" />
                                <span>Sudah disetujui</span>
                            </div>
                        </div>
                    </section>

                    <section className="overflow-x-auto rounded-[24px] border border-[var(--color-border)] bg-white/90 p-5 shadow-sm sm:p-6">
                        <div className="mx-auto min-w-[900px] max-w-[1120px] space-y-8">
                            <div className="mx-auto flex h-16 w-[220px] items-center justify-center rounded-md border border-slate-500 bg-slate-200 text-sm font-semibold text-slate-800">
                                Mesin kremasi
                            </div>

                            <div className="flex items-start justify-center gap-12">
                                <div className="grid grid-cols-4 gap-6">
                                    {leftRows.map((row) => (
                                        <div key={row.row_code} className="space-y-3">
                                            <div className="grid gap-1">
                                                {row.slots.map((slot) =>
                                                    slot.booking_id ? (
                                                        <Link
                                                            key={slot.id}
                                                            href={`/admin/booking/${slot.booking_id}`}
                                                            title={slotTitle(slot)}
                                                            className={`flex h-8 w-14 items-center justify-center rounded border text-xs font-medium transition hover:scale-[1.02] ${slotTone(slot.status)}`}
                                                        >
                                                            {slot.number}
                                                        </Link>
                                                    ) : (
                                                        <div
                                                            key={slot.id}
                                                            title={slotTitle(slot)}
                                                            className={`flex h-8 w-14 items-center justify-center rounded border text-xs font-medium ${slotTone(slot.status)}`}
                                                        >
                                                            {slot.number}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                            <div className="rounded bg-yellow-300 px-3 py-1 text-center text-xs font-semibold text-slate-900">
                                                Row {row.row_code}
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <div className="w-16 shrink-0" />

                                <div className="grid grid-cols-4 gap-6">
                                    {rightRows.map((row) => (
                                        <div key={row.row_code} className="space-y-3">
                                            <div className="grid gap-1">
                                                {row.slots.map((slot) =>
                                                    slot.booking_id ? (
                                                        <Link
                                                            key={slot.id}
                                                            href={`/admin/booking/${slot.booking_id}`}
                                                            title={slotTitle(slot)}
                                                            className={`flex h-8 w-14 items-center justify-center rounded border text-xs font-medium transition hover:scale-[1.02] ${slotTone(slot.status)}`}
                                                        >
                                                            {slot.number}
                                                        </Link>
                                                    ) : (
                                                        <div
                                                            key={slot.id}
                                                            title={slotTitle(slot)}
                                                            className={`flex h-8 w-14 items-center justify-center rounded border text-xs font-medium ${slotTone(slot.status)}`}
                                                        >
                                                            {slot.number}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                            <div className="rounded bg-yellow-300 px-3 py-1 text-center text-xs font-semibold text-slate-900">
                                                Row {row.row_code}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="mx-auto flex h-20 w-[220px] items-center justify-center rounded-md border border-sky-500 bg-sky-200 text-sm font-semibold text-slate-800">
                                Altar
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
