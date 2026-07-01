import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { Auth } from '@/types';

type ResultItem = {
    booking_id: number;
    booking_number: string;
    customer_name?: string;
    attendee_count?: number;
    vegetarian_quantity?: number;
    non_vegetarian_quantity?: number;
    table_codes?: string[];
    incense_numbers?: number[];
    check_in_status?: string;
    checked_in_at?: string | null;
    checked_in_by?: string | null;
    status?: string;
};

type Props = {
    auth: Auth;
    lookup_code: string;
    lookup_error: string | null;
    result: ResultItem | null;
    flash?: {
        status?: string | null;
    };
};

type BarcodeDetectorConstructor = {
    new (options?: { formats?: string[] }): {
        detect: (
            source: ImageBitmapSource,
        ) => Promise<Array<{ rawValue?: string }>>;
    };
};

declare global {
    interface Window {
        BarcodeDetector?: BarcodeDetectorConstructor;
    }
}

export default function CheckerDashboard() {
    const { lookup_code, lookup_error, result, flash } = usePage<Props>().props;
    const form = useForm({
        kode: lookup_code,
    });
    const [scannerError, setScannerError] = useState<string | null>(null);
    const [scannerActive, setScannerActive] = useState(false);
    const [scannerLoading, setScannerLoading] = useState(false);
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const intervalRef = useRef<number | null>(null);
    const streamRef = useRef<MediaStream | null>(null);

    const canUseScanner = useMemo(() => {
        return (
            typeof window !== 'undefined' &&
            'mediaDevices' in navigator &&
            typeof window.BarcodeDetector !== 'undefined'
        );
    }, []);

    const stopScanner = () => {
        if (intervalRef.current !== null) {
            window.clearInterval(intervalRef.current);
            intervalRef.current = null;
        }

        if (streamRef.current) {
            for (const track of streamRef.current.getTracks()) {
                track.stop();
            }

            streamRef.current = null;
        }

        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }

        setScannerActive(false);
        setScannerLoading(false);
    };

    useEffect(() => {
        return () => {
            stopScanner();
        };
    }, []);

    const submitLookup = (code: string) => {
        router.get(
            '/checker',
            { kode: code },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const startScanner = async () => {
        if (!canUseScanner) {
            setScannerError('Kamera scan belum didukung di browser ini.');

            return;
        }

        try {
            setScannerError(null);
            setScannerLoading(true);

            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });

            streamRef.current = stream;

            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
            }

            const Detector = window.BarcodeDetector;

            if (!Detector) {
                throw new Error('Scanner tidak tersedia.');
            }

            const detector = new Detector({
                formats: ['qr_code'],
            });

            intervalRef.current = window.setInterval(async () => {
                if (!videoRef.current) {
                    return;
                }

                try {
                    const codes = await detector.detect(videoRef.current);
                    const value = codes[0]?.rawValue?.trim();

                    if (!value) {
                        return;
                    }

                    form.setData('kode', value);
                    stopScanner();
                    submitLookup(value);
                } catch {
                    // biarkan scan berikutnya mencoba lagi
                }
            }, 700);

            setScannerActive(true);
            setScannerLoading(false);
        } catch {
            stopScanner();
            setScannerError(
                'Kamera tidak bisa dibuka. Silakan pakai input manual.',
            );
        }
    };

    const manualLookup = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        submitLookup(form.data.kode);
    };

    const checkIn = () => {
        if (!result?.booking_id) {
            return;
        }

        if (!window.confirm('Catat tamu ini sebagai sudah masuk?')) {
            return;
        }

        router.post(`/checker/check-in/${result.booking_id}`);
    };

    return (
        <>
            <Head title="Check-in" />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-4xl space-y-6">
                    <section className="rounded-[24px] border border-[var(--color-border)] bg-[var(--color-panel)] p-6 shadow-sm sm:p-8">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h1 className="mt-3 text-3xl font-semibold">
                                    Check-in tamu
                                </h1>
                                <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-700">
                                    Scan QR atau masukkan kode booking untuk
                                    mencari tamu.
                                </p>
                            </div>

                            <div className="flex gap-3">
                                <Link
                                    href="/keluar"
                                    method="post"
                                    as="button"
                                    className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                                >
                                    Keluar
                                </Link>
                            </div>
                        </div>
                    </section>

                    {flash?.status ? (
                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.status}
                        </div>
                    ) : null}

                    <section className="space-y-4 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Scan QR
                                </h2>
                                <p className="mt-1 text-sm text-slate-700">
                                    Arahkan kamera ke QR tamu.
                                </p>
                            </div>

                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={
                                        scannerActive
                                            ? stopScanner
                                            : () => void startScanner()
                                    }
                                    className="rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white"
                                >
                                    {scannerLoading
                                        ? 'Membuka kamera...'
                                        : scannerActive
                                          ? 'Hentikan scan'
                                          : 'Mulai scan'}
                                </button>
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-[20px] border border-[var(--color-border)] bg-slate-950">
                            <video
                                ref={videoRef}
                                muted
                                playsInline
                                className="aspect-[4/3] w-full object-cover"
                            />
                        </div>

                        {scannerError ? (
                            <p className="text-sm text-red-700">
                                {scannerError}
                            </p>
                        ) : null}
                        {!canUseScanner ? (
                            <p className="text-sm text-slate-700">
                                Browser ini belum mendukung scan kamera. Gunakan
                                input manual.
                            </p>
                        ) : null}
                    </section>

                    <section className="space-y-4 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Input manual
                            </h2>
                            <p className="mt-1 text-sm text-slate-700">
                                Bisa isi nomor booking atau kode dari hasil
                                scan.
                            </p>
                        </div>

                        <form onSubmit={manualLookup} className="space-y-4">
                            <input
                                type="text"
                                value={form.data.kode}
                                onChange={(event) =>
                                    form.setData('kode', event.target.value)
                                }
                                placeholder="Contoh: CD-XXXXXXX"
                                className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-4 text-base"
                            />
                            <button
                                type="submit"
                                className="w-full rounded-full bg-[var(--color-brand)] px-5 py-4 text-base font-semibold text-white"
                            >
                                Cari tamu
                            </button>
                        </form>

                        {lookup_error ? (
                            <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                                {lookup_error}
                            </div>
                        ) : null}
                    </section>

                    {result ? (
                        <section className="space-y-5 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Hasil pencarian
                                </h2>
                                <p className="mt-1 text-sm text-slate-700">
                                    {result.booking_number}
                                </p>
                            </div>

                            {result.customer_name ? (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="rounded-2xl border border-[var(--color-border)] p-4">
                                            <p className="text-sm text-slate-500">
                                                Nama pemesan
                                            </p>
                                            <p className="mt-1 text-base font-semibold text-[var(--color-ink)]">
                                                {result.customer_name}
                                            </p>
                                        </div>

                                        <div className="rounded-2xl border border-[var(--color-border)] p-4">
                                            <p className="text-sm text-slate-500">
                                                Jumlah hadir
                                            </p>
                                            <p className="mt-1 text-base font-semibold text-[var(--color-ink)]">
                                                {result.attendee_count}
                                            </p>
                                        </div>

                                        <div className="rounded-2xl border border-[var(--color-border)] p-4">
                                            <p className="text-sm text-slate-500">
                                                Nomor meja
                                            </p>
                                            <p className="mt-1 text-base font-semibold text-[var(--color-ink)]">
                                                {result.table_codes?.join(
                                                    ', ',
                                                ) || '-'}
                                            </p>
                                        </div>

                                        <div className="rounded-2xl border border-[var(--color-border)] p-4">
                                            <p className="text-sm text-slate-500">
                                                Nomor hio
                                            </p>
                                            <p className="mt-1 text-base font-semibold text-[var(--color-ink)]">
                                                {result.incense_numbers?.join(
                                                    ', ',
                                                ) || '-'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-[var(--color-border)] p-4">
                                        <p className="text-sm text-slate-500">
                                            Makanan
                                        </p>
                                        <p className="mt-1 text-base font-semibold text-[var(--color-ink)]">
                                            Vegetarian{' '}
                                            {result.vegetarian_quantity ?? 0} |
                                            Non vegetarian{' '}
                                            {result.non_vegetarian_quantity ??
                                                0}
                                        </p>
                                    </div>

                                    {result.check_in_status ===
                                    'SUDAH_MASUK' ? (
                                        <div className="space-y-2 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                                            <p className="font-semibold">
                                                Tamu ini sudah masuk.
                                            </p>
                                            <p>
                                                Waktu:{' '}
                                                {result.checked_in_at ?? '-'}
                                            </p>
                                            <p>
                                                Petugas:{' '}
                                                {result.checked_in_by ?? '-'}
                                            </p>
                                        </div>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={checkIn}
                                            className="w-full rounded-full bg-emerald-600 px-5 py-4 text-base font-semibold text-white"
                                        >
                                            Catat check-in
                                        </button>
                                    )}
                                </>
                            ) : (
                                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                    Booking ini belum bisa diproses untuk masuk.
                                </div>
                            )}
                        </section>
                    ) : null}
                </div>
            </main>
        </>
    );
}
