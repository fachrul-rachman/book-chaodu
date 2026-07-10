import { Head, Link, usePage } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { formatCurrency } from '@/lib/booking';

type Props = {
    booking: {
        booking_number: string;
        customer_name: string;
        customer_email: string;
        attendee_count: number;
        package_name: string;
        package_price: string;
        status: string;
        table_slot: string | null;
        incense_slot: number | null;
        sender_name: string | null;
        transfer_date: string | null;
        proof_name: string | null;
        virtual_account_bank_name: string | null;
        virtual_account_number: string | null;
        virtual_account_holder: string | null;
        deceased_names: Array<{
            position: number;
            indonesian_name: string | null;
            mandarin_name: string | null;
        }>;
        incense_name: {
            indonesian_name: string | null;
            mandarin_name: string | null;
        };
        expires_at: string | null;
        payment_url: string;
        is_expired: boolean;
        is_waiting_payment: boolean;
        is_waiting_review: boolean;
    };
    limits: {
        upload_max_mb: number;
    };
};

type ErrorBag = Record<string, string>;

export default function PublicBookingPaymentPage() {
    const { booking, limits } = usePage<Props>().props;
    const [senderName, setSenderName] = useState(booking.sender_name ?? '');
    const [transferDate, setTransferDate] = useState(booking.transfer_date ?? '');
    const [proof, setProof] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [paymentSubmitted, setPaymentSubmitted] = useState(false);
    const [generalError, setGeneralError] = useState<string | null>(null);
    const [errors, setErrors] = useState<ErrorBag>({});
    const [copiedVa, setCopiedVa] = useState(false);
    const [copiedAmount, setCopiedAmount] = useState(false);

    const expiresAtLabel = useMemo(() => {
        if (!booking.expires_at) {
            return '-';
        }

        const value = new Date(booking.expires_at);

        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(value);
    }, [booking.expires_at]);

    const copyText = async (value: string | null, kind: 'va' | 'amount') => {
        if (!value) {
            return;
        }

        await navigator.clipboard.writeText(value);

        if (kind === 'va') {
            setCopiedVa(true);
            window.setTimeout(() => setCopiedVa(false), 2000);

            return;
        }

        setCopiedAmount(true);
        window.setTimeout(() => setCopiedAmount(false), 2000);
    };

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!booking.is_waiting_payment) {
            return;
        }

        const nextErrors: ErrorBag = {};

        if (!senderName.trim()) {
            nextErrors.sender_name = 'Nama pengirim wajib diisi.';
        }

        if (!transferDate) {
            nextErrors.transfer_date = 'Tanggal transfer wajib diisi.';
        }

        if (!proof) {
            nextErrors.proof = 'Bukti transfer wajib diunggah.';
        }

        setErrors(nextErrors);

        if (Object.keys(nextErrors).length > 0) {
            return;
        }

        setProcessing(true);
        setGeneralError(null);
        setSuccessMessage(null);

        const token = new URL(booking.payment_url).searchParams.get('token') ?? '';
        const payload = new FormData();
        payload.append('token', token);
        payload.append('sender_name', senderName);
        payload.append('transfer_date', transferDate);

        if (proof) {
            payload.append('proof', proof);
        }

        try {
            const response = await fetch(booking.payment_url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
            });

            if (response.status === 422) {
                const data = (await response.json()) as {
                    errors?: Record<string, string[]>;
                    message?: string;
                };
                const nextValidationErrors = Object.fromEntries(
                    Object.entries(data.errors ?? {}).map(([key, value]) => [
                        key,
                        value[0] ?? 'Data belum benar.',
                    ]),
                );

                setErrors(nextValidationErrors);
                setGeneralError(
                    data.message ?? 'Beberapa data masih perlu diperbaiki.',
                );

                return;
            }

            if (!response.ok) {
                setGeneralError(
                    'Pembayaran belum berhasil dikirim. Silakan coba lagi.',
                );

                return;
            }

            const data = (await response.json()) as { message: string };
            setSuccessMessage(data.message);
            setPaymentSubmitted(true);
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title={`Pembayaran ${booking.booking_number}`} />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-5xl space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-semibold text-[var(--color-brand)]">
                                Pembayaran booking
                            </p>
                            <h1 className="mt-2 text-3xl font-semibold text-[var(--color-ink)]">
                                {booking.booking_number}
                            </h1>
                        </div>

                        <Link
                            href="/"
                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </div>

                    {successMessage ? (
                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {successMessage}
                        </div>
                    ) : null}

                    {generalError ? (
                        <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {generalError}
                        </div>
                    ) : null}

                    <section className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                        <div className="space-y-6 rounded-[24px] border border-[var(--color-border)] bg-white/95 p-6 shadow-sm">
                            <div>
                                <h2 className="text-lg font-semibold text-[var(--color-ink)]">
                                    Data booking
                                </h2>
                                <div className="mt-4 space-y-2 text-sm leading-7 text-slate-700">
                                    <p>Nama: {booking.customer_name}</p>
                                    <p>Email: {booking.customer_email}</p>
                                    <p>Jumlah hadir: {booking.attendee_count}</p>
                                    <p>Paket: {booking.package_name}</p>
                                    <p>
                                        Nominal bayar:{' '}
                                        {formatCurrency(booking.package_price)}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <h2 className="text-lg font-semibold text-[var(--color-ink)]">
                                    Nama yang didaftarkan
                                </h2>
                                <div className="mt-4 space-y-3 text-sm leading-7 text-slate-700">
                                    {booking.deceased_names.map((item) => (
                                        <p key={item.position}>
                                            {item.indonesian_name || '-'} /{' '}
                                            {item.mandarin_name || '-'}
                                        </p>
                                    ))}
                                    {booking.incense_name.indonesian_name ||
                                    booking.incense_name.mandarin_name ? (
                                        <p>
                                            {booking.incense_name
                                                .indonesian_name || '-'}{' '}
                                            /{' '}
                                            {booking.incense_name.mandarin_name ||
                                                '-'}
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                        </div>

                        <div className="space-y-6 rounded-[24px] border border-[var(--color-border)] bg-white/95 p-6 shadow-sm">
                            <div>
                                <p className="text-base text-[var(--color-brand)]">
                                    Total yang harus dibayar
                                </p>
                                <p className="mt-2 text-4xl font-semibold text-[var(--color-brand)]">
                                    {formatCurrency(booking.package_price)}
                                </p>
                                <button
                                    type="button"
                                    onClick={() =>
                                        copyText(
                                            String(Math.round(Number(booking.package_price))),
                                            'amount',
                                        )
                                    }
                                    className="mt-4 rounded-full border-2 border-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-[var(--color-brand)]"
                                >
                                    {copiedAmount ? 'Nominal tersalin' : 'Salin nominal bayar'}
                                </button>
                            </div>

                            <div className="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-5 text-sm leading-8 text-amber-900">
                                <p className="font-semibold text-[var(--color-ink)]">
                                    Catatan penting saat transfer
                                </p>
                                <p className="mt-2">
                                    Pastikan menuliskan nama paket yang diambil saat transfer, supaya lebih mudah dicek oleh petugas.
                                </p>
                            </div>

                            <div className="space-y-2 text-base leading-8 text-[var(--color-ink)]">
                                <p>
                                    Bank: {booking.virtual_account_bank_name || '-'}
                                </p>
                                <p>
                                    Nomor VA:{' '}
                                    <strong>{booking.virtual_account_number || '-'}</strong>
                                </p>
                                <p>
                                    Atas nama: {booking.virtual_account_holder || '-'}
                                </p>
                                <p>
                                    Batas waktu: <strong>{expiresAtLabel}</strong>
                                </p>
                            </div>

                            <div className="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-7 text-amber-900">
                                <p className="font-semibold">
                                    Jangan transfer jika waktu booking ini sudah habis.
                                </p>
                                <p className="mt-2">
                                    Jika waktu sudah habis, transfer tidak boleh dilakukan dan booking harus dibuat ulang.
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() =>
                                    copyText(
                                        booking.virtual_account_number,
                                        'va',
                                    )
                                }
                                className="rounded-full border-2 border-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-[var(--color-brand)]"
                            >
                                {copiedVa ? 'Nomor VA tersalin' : 'Salin nomor VA'}
                            </button>

                            {paymentSubmitted ? (
                                <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm leading-7 text-emerald-800">
                                    <p className="font-semibold text-base text-emerald-900">
                                        Pembayaran berhasil dikirim.
                                    </p>
                                    <p className="mt-2">
                                        Silakan cek email untuk mendapatkan QR code.
                                    </p>
                                    <div className="mt-4 rounded-2xl bg-white/80 px-4 py-4 text-slate-800">
                                        <p className="font-semibold text-[var(--color-ink)]">
                                            Informasi booking Anda
                                        </p>
                                        {booking.table_slot ? (
                                            <p className="mt-2">
                                                Nomor meja: <strong>{booking.table_slot}</strong>
                                            </p>
                                        ) : null}
                                        {booking.incense_slot ? (
                                            <p className={booking.table_slot ? 'mt-1' : 'mt-2'}>
                                                Nomor hio: <strong>{booking.incense_slot}</strong>
                                            </p>
                                        ) : null}
                                        {!booking.table_slot && !booking.incense_slot ? (
                                            <p className="mt-2">
                                                Nomor booking: <strong>{booking.booking_number}</strong>
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                            ) : booking.is_expired ? (
                                <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm leading-7 text-red-800">
                                    Booking ini sudah lewat waktu. Silakan lakukan booking ulang.
                                </div>
                            ) : booking.is_waiting_review ? (
                                <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm leading-7 text-emerald-800">
                                    Pembayaran sudah dikirim. Mohon tunggu pengecekan dari petugas.
                                </div>
                            ) : null}

                            {booking.is_waiting_payment && !paymentSubmitted ? (
                                <form onSubmit={submit} className="space-y-4">
                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium text-[var(--color-ink)]">
                                            Nama pengirim
                                        </span>
                                        <input
                                            type="text"
                                            value={senderName}
                                            onChange={(event) =>
                                                setSenderName(event.target.value)
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                        {errors.sender_name ? (
                                            <p className="mt-2 text-sm text-red-700">
                                                {errors.sender_name}
                                            </p>
                                        ) : null}
                                    </label>

                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium text-[var(--color-ink)]">
                                            Tanggal transfer
                                        </span>
                                        <input
                                            type="date"
                                            value={transferDate}
                                            onChange={(event) =>
                                                setTransferDate(event.target.value)
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                        {errors.transfer_date ? (
                                            <p className="mt-2 text-sm text-red-700">
                                                {errors.transfer_date}
                                            </p>
                                        ) : null}
                                    </label>

                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium text-[var(--color-ink)]">
                                            Bukti transfer
                                        </span>
                                        <input
                                            type="file"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            onChange={(
                                                event: ChangeEvent<HTMLInputElement>,
                                            ) =>
                                                setProof(
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            className="block w-full text-sm text-slate-700"
                                        />
                                        <p className="mt-2 text-sm text-slate-600">
                                            Format JPG, PNG, atau PDF. Maksimal{' '}
                                            {limits.upload_max_mb} MB.
                                        </p>
                                        {proof ? (
                                            <p className="mt-2 text-sm text-slate-700">
                                                {proof.name}
                                            </p>
                                        ) : booking.proof_name ? (
                                            <p className="mt-2 text-sm text-slate-700">
                                                {booking.proof_name}
                                            </p>
                                        ) : null}
                                        {errors.proof ? (
                                            <p className="mt-2 text-sm text-red-700">
                                                {errors.proof}
                                            </p>
                                        ) : null}
                                    </label>

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60"
                                    >
                                        {processing
                                            ? 'Mengirim...'
                                            : 'Kirim pembayaran'}
                                    </button>
                                </form>
                            ) : null}
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
