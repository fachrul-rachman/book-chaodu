import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

type NameRow = {
    position: number;
    indonesian_name: string;
    mandarin_name: string;
};

type SlotItem = {
    id: number;
    code?: string;
    number?: number;
    status: string;
};

type BookingDetail = {
    id: number;
    booking_number: string;
    status: string;
    package_name: string;
    customer_name: string;
    customer_phone: string;
    customer_email: string;
    attendee_count: number;
    referral_source: string;
    agent_name: string | null;
    rejection_reason: string | null;
    proof_path: string | null;
    proof_url: string | null;
    virtual_account_bank_name: string | null;
    virtual_account_number: string | null;
    virtual_account_holder: string | null;
    sender_name: string | null;
    transferred_amount: string | null;
    transfer_date: string | null;
    vegetarian_quantity: number;
    non_vegetarian_quantity: number;
    deceased_names: NameRow[];
    incense_name: NameRow;
    table_slots: SlotItem[];
    incense_slots: SlotItem[];
    prayer_paper_status: string | null;
    prayer_papers: Array<{
        id: number;
        type: string;
        sequence: number;
        status: string;
        file_url: string | null;
    }>;
    approval_integration: {
        qr_status: string;
        qr_error: string | null;
        qr_url: string | null;
        drive_status: string;
        drive_error: string | null;
        drive_url: string | null;
        notion_status: string;
        notion_error: string | null;
        notion_url: string | null;
        approval_email_status: string;
        approval_email_error: string | null;
        approval_email_sent_at: string | null;
        retry_urls: Record<string, string>;
    } | null;
};

type Props = {
    booking: BookingDetail;
    slot_options: {
        tables: SlotItem[];
        incense: SlotItem[];
    };
    flash?: {
        status?: string | null;
    };
    errors: Record<string, string | undefined>;
};

function formatNominalInput(value: string): string {
    const digitsOnly = value.replace(/\D/g, '');

    if (!digitsOnly) {
        return '';
    }

    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(Number(digitsOnly));
}

function formatStoredNominalInput(value: string): string {
    const numeric = Number(value);

    if (!Number.isFinite(numeric) || numeric <= 0) {
        return '';
    }

    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(numeric);
}

function prayerPaperLabel(type: string): string {
    return type === 'A' ? 'Kertas Doa' : 'Kertas Hio';
}

export default function AdminBookingShowPage() {
    const { booking, slot_options, flash, errors } = usePage<Props>().props;
    const form = useForm({
        customer_name: booking.customer_name,
        customer_phone: booking.customer_phone,
        customer_email: booking.customer_email,
        attendee_count: String(booking.attendee_count),
        sender_name: booking.sender_name ?? '',
        transferred_amount: booking.transferred_amount
            ? formatStoredNominalInput(booking.transferred_amount)
            : '',
        transfer_date: booking.transfer_date ?? '',
        referral_source: booking.referral_source,
        agent_name: booking.agent_name ?? '',
        vegetarian_quantity: String(booking.vegetarian_quantity),
        non_vegetarian_quantity: String(booking.non_vegetarian_quantity),
        replace_table_slot_id: '',
        replace_incense_slot_id: '',
        deceased_names: booking.deceased_names.map((name) => ({ ...name })),
        incense_name: { ...booking.incense_name },
    });
    const rejectForm = useForm({
        reason: booking.rejection_reason ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            transferred_amount: data.transferred_amount.replace(/\./g, ''),
            attendee_count: Number(data.attendee_count),
            vegetarian_quantity: Number(data.vegetarian_quantity),
            non_vegetarian_quantity: Number(data.non_vegetarian_quantity),
            replace_table_slot_id: data.replace_table_slot_id || null,
            replace_incense_slot_id: data.replace_incense_slot_id || null,
            agent_name: data.agent_name.trim() || null,
            incense_name:
                booking.incense_slots.length > 0 ||
                data.incense_name.indonesian_name ||
                data.incense_name.mandarin_name
                    ? data.incense_name
                    : null,
        }));

        form.put(`/admin/booking/${booking.id}`);
    };

    const approve = () => {
        if (!window.confirm('Setujui booking ini?')) {
            return;
        }

        form.post(`/admin/booking/${booking.id}/setuju`);
    };

    const reject = () => {
        if (!window.confirm('Tolak booking ini?')) {
            return;
        }

        rejectForm.post(`/admin/booking/${booking.id}/tolak`);
    };

    const integration = booking.approval_integration;

    return (
        <>
            <Head title={booking.booking_number} />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-5xl space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold">
                                {booking.booking_number}
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                {booking.package_name} | {booking.status}
                            </p>
                        </div>

                        <Link
                            href="/admin/booking"
                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </div>

                    {flash?.status ? (
                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.status}
                        </div>
                    ) : null}

                    {Object.keys(errors).length > 0 ? (
                        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {Object.values(errors).filter(Boolean).join(' ')}
                        </div>
                    ) : null}

                    <section className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-1 text-sm text-slate-700">
                                <p>
                                    Meja:{' '}
                                    {booking.table_slots
                                        .map((slot) => slot.code)
                                        .filter(Boolean)
                                        .join(', ') || '-'}
                                </p>
                                <p>
                                    VA:{' '}
                                    {booking.virtual_account_number
                                        ? `${booking.virtual_account_bank_name ?? '-'} | ${booking.virtual_account_number} | ${booking.virtual_account_holder ?? '-'}`
                                        : '-'}
                                </p>
                                <p>
                                    Hio:{' '}
                                    {booking.incense_slots
                                        .map((slot) => slot.number)
                                        .filter((value) => value !== undefined)
                                        .join(', ') || '-'}
                                </p>
                                <p>
                                    Status file nama:{' '}
                                    {booking.prayer_paper_status ?? '-'}
                                </p>
                                <p>{booking.proof_path ?? ''}</p>
                            </div>

                            <div className="flex flex-col gap-2">
                                {booking.proof_url ? (
                                    <a
                                        href={booking.proof_url}
                                        className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-center text-sm font-semibold text-[var(--color-brand)]"
                                    >
                                        Buka bukti transfer
                                    </a>
                                ) : null}
                                {booking.prayer_papers.map((item) =>
                                    item.file_url ? (
                                        <a
                                            key={item.id}
                                            href={item.file_url}
                                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-center text-sm font-semibold text-[var(--color-brand)]"
                                        >
                                            Buka {prayerPaperLabel(item.type)}
                                            {item.type === 'A'
                                                ? ` ${item.sequence}`
                                                : ''}
                                        </a>
                                    ) : null,
                                )}
                            </div>
                        </div>
                    </section>

                    {integration ? (
                        <section className="space-y-4 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                            <h2 className="text-lg font-semibold">
                                Status approval
                            </h2>

                            {[
                                {
                                    key: 'qr',
                                    title: 'QR',
                                    status: integration.qr_status,
                                    error: integration.qr_error,
                                    url: integration.qr_url,
                                },
                                {
                                    key: 'drive',
                                    title: 'Google Drive',
                                    status: integration.drive_status,
                                    error: integration.drive_error,
                                    url: integration.drive_url,
                                },
                                {
                                    key: 'notion',
                                    title: 'Notion',
                                    status: integration.notion_status,
                                    error: integration.notion_error,
                                    url: integration.notion_url,
                                },
                                {
                                    key: 'approval_email',
                                    title: 'Email approval',
                                    status: integration.approval_email_status,
                                    error: integration.approval_email_error,
                                    url: null,
                                },
                            ].map((item) => (
                                <div
                                    key={item.key}
                                    className="flex flex-col gap-3 rounded-2xl border border-[var(--color-border)] p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="space-y-1 text-sm text-slate-700">
                                        <p className="font-semibold text-[var(--color-ink)]">
                                            {item.title}
                                        </p>
                                        <p>Status: {item.status}</p>
                                        {item.key === 'approval_email' &&
                                        integration.approval_email_sent_at ? (
                                            <p>
                                                Terkirim:{' '}
                                                {
                                                    integration.approval_email_sent_at
                                                }
                                            </p>
                                        ) : null}
                                        {item.error ? (
                                            <p>{item.error}</p>
                                        ) : null}
                                        {item.url ? (
                                            <a
                                                href={item.url}
                                                className="inline-block text-sm font-semibold text-[var(--color-brand)]"
                                            >
                                                Buka
                                            </a>
                                        ) : null}
                                    </div>

                                    <Link
                                        href={integration.retry_urls[item.key]}
                                        method="post"
                                        as="button"
                                        className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                                    >
                                        Retry
                                    </Link>
                                </div>
                            ))}
                        </section>
                    ) : null}

                    <form
                        onSubmit={submit}
                        className="space-y-6 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nama customer
                                </span>
                                <input
                                    type="text"
                                    value={form.data.customer_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'customer_name',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nomor telepon
                                </span>
                                <input
                                    type="tel"
                                    value={form.data.customer_phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'customer_phone',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Email
                                </span>
                                <input
                                    type="email"
                                    value={form.data.customer_email}
                                    onChange={(event) =>
                                        form.setData(
                                            'customer_email',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Jumlah hadir
                                </span>
                                <input
                                    type="number"
                                    min={1}
                                    value={form.data.attendee_count}
                                    onChange={(event) =>
                                        form.setData(
                                            'attendee_count',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nama pengirim
                                </span>
                                <input
                                    type="text"
                                    value={form.data.sender_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'sender_name',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Nominal transfer
                                </span>
                                <input
                                    type="text"
                                    inputMode="numeric"
                                    value={form.data.transferred_amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'transferred_amount',
                                            formatNominalInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Tanggal transfer
                                </span>
                                <input
                                    type="date"
                                    value={form.data.transfer_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'transfer_date',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Sumber informasi
                                </span>
                                <select
                                    value={form.data.referral_source}
                                    onChange={(event) =>
                                        form.setData(
                                            'referral_source',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                >
                                    <option value="TEMAN">Teman</option>
                                    <option value="KELUARGA">Keluarga</option>
                                    <option value="MEDIA_SOSIAL">
                                        Media sosial
                                    </option>
                                    <option value="WEBSITE">Website</option>
                                    <option value="AGENT">Agent</option>
                                </select>
                            </label>

                            <label className="block sm:col-span-2">
                                <span className="mb-2 block text-sm font-medium">
                                    Nama agent
                                </span>
                                <input
                                    type="text"
                                    value={form.data.agent_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'agent_name',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {form.data.deceased_names.map((name, index) => (
                                <div
                                    key={name.position}
                                    className="space-y-3 rounded-2xl border border-[var(--color-border)] p-4"
                                >
                                    <p className="text-sm font-semibold">
                                        Nama {index + 1}
                                    </p>
                                    <input
                                        type="text"
                                        value={name.indonesian_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'deceased_names',
                                                form.data.deceased_names.map(
                                                    (item, itemIndex) =>
                                                        itemIndex === index
                                                            ? {
                                                                  ...item,
                                                                  indonesian_name:
                                                                      event
                                                                          .target
                                                                          .value,
                                                              }
                                                            : item,
                                                ),
                                            )
                                        }
                                        placeholder="Nama Indonesia"
                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                    />
                                    <input
                                        type="text"
                                        value={name.mandarin_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'deceased_names',
                                                form.data.deceased_names.map(
                                                    (item, itemIndex) =>
                                                        itemIndex === index
                                                            ? {
                                                                  ...item,
                                                                  mandarin_name:
                                                                      event
                                                                          .target
                                                                          .value,
                                                              }
                                                            : item,
                                                ),
                                            )
                                        }
                                        placeholder="Nama Mandarin"
                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                    />
                                </div>
                            ))}
                        </div>

                        {booking.incense_slots.length > 0 ? (
                            <div className="space-y-3 rounded-2xl border border-[var(--color-border)] p-4">
                                <p className="text-sm font-semibold">
                                    Nama hio
                                </p>
                                <input
                                    type="text"
                                    value={
                                        form.data.incense_name.indonesian_name
                                    }
                                    onChange={(event) =>
                                        form.setData('incense_name', {
                                            ...form.data.incense_name,
                                            indonesian_name: event.target.value,
                                        })
                                    }
                                    placeholder="Nama Indonesia"
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                                <input
                                    type="text"
                                    value={form.data.incense_name.mandarin_name}
                                    onChange={(event) =>
                                        form.setData('incense_name', {
                                            ...form.data.incense_name,
                                            mandarin_name: event.target.value,
                                        })
                                    }
                                    placeholder="Nama Mandarin"
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </div>
                        ) : null}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Makanan vegetarian
                                </span>
                                <input
                                    type="number"
                                    min={0}
                                    value={form.data.vegetarian_quantity}
                                    onChange={(event) =>
                                        form.setData(
                                            'vegetarian_quantity',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>

                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Makanan non vegetarian
                                </span>
                                <input
                                    type="number"
                                    min={0}
                                    value={form.data.non_vegetarian_quantity}
                                    onChange={(event) =>
                                        form.setData(
                                            'non_vegetarian_quantity',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                />
                            </label>
                        </div>

                        {booking.table_slots.length > 0 ? (
                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Ganti nomor meja
                                </span>
                                <select
                                    value={form.data.replace_table_slot_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'replace_table_slot_id',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                >
                                    <option value="">
                                        Tetap pakai nomor sekarang
                                    </option>
                                    {slot_options.tables.map((slot) => (
                                        <option key={slot.id} value={slot.id}>
                                            {slot.code}{' '}
                                            {booking.table_slots.some(
                                                (item) => item.id === slot.id,
                                            )
                                                ? '(sekarang)'
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ) : null}

                        {booking.incense_slots.length > 0 ? (
                            <label className="block">
                                <span className="mb-2 block text-sm font-medium">
                                    Ganti nomor hio
                                </span>
                                <select
                                    value={form.data.replace_incense_slot_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'replace_incense_slot_id',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                >
                                    <option value="">
                                        Tetap pakai nomor sekarang
                                    </option>
                                    {slot_options.incense.map((slot) => (
                                        <option key={slot.id} value={slot.id}>
                                            {slot.number}{' '}
                                            {booking.incense_slots.some(
                                                (item) => item.id === slot.id,
                                            )
                                                ? '(sekarang)'
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ) : null}

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {form.processing
                                ? 'Menyimpan...'
                                : 'Simpan perubahan'}
                        </button>
                    </form>

                    <section className="space-y-4 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                        <button
                            type="button"
                            onClick={approve}
                            className="rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white"
                        >
                            Setujui booking
                        </button>

                        <div className="space-y-3">
                            <textarea
                                value={rejectForm.data.reason}
                                onChange={(event) =>
                                    rejectForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                rows={4}
                                placeholder="Alasan penolakan"
                                className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                            />
                            <button
                                type="button"
                                onClick={reject}
                                className="rounded-full border border-red-300 px-5 py-3 text-sm font-semibold text-red-700"
                            >
                                Tolak booking
                            </button>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
