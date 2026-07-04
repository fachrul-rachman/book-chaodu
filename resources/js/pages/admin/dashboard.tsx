import { Head, Link, usePage } from '@inertiajs/react';
import type { Auth } from '@/types';

type Availability = {
    table_remaining: number;
    incense_remaining: number;
};

export default function AdminDashboard() {
    const { auth, availability, booking_counts } = usePage<{
        auth: Auth;
        availability: Availability;
        booking_counts: {
            pending: number;
            approved: number;
            rejected: number;
        };
    }>().props;
    const user = auth.user!;

    return (
        <>
            <Head title="Halaman Utama" />

            <main className="min-h-screen bg-[var(--color-bg,#f8fafc)] px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-5xl space-y-8">
                    {/* Header */}
                    <section className="rounded-[24px] border border-[var(--color-border)] bg-[var(--color-panel)] p-6 shadow-sm sm:p-8">
                        <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Dashboard Admin
                                </p>
                                <h1 className="mt-1 text-2xl font-semibold text-slate-900 sm:text-3xl">
                                    Selamat datang, {user.name}
                                </h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                                    Silakan lanjutkan pekerjaan Anda dari
                                    halaman ini.
                                </p>
                            </div>

                            <Link
                                href="/keluar"
                                method="post"
                                as="button"
                                className="w-fit shrink-0 rounded-full border border-[var(--color-brand)] px-5 py-2 text-sm font-semibold text-[var(--color-brand)] transition-colors hover:bg-[var(--color-brand)] hover:text-white"
                            >
                                Keluar
                            </Link>
                        </div>
                    </section>

                    {/* Sisa nomor */}
                    <section className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm sm:p-7">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Sisa nomor
                        </h2>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="rounded-2xl bg-slate-50 p-4">
                                <p className="text-xs font-medium text-slate-500">
                                    Nomor meja tersisa
                                </p>
                                <p className="mt-1 text-2xl font-semibold text-slate-900">
                                    {availability.table_remaining}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 p-4">
                                <p className="text-xs font-medium text-slate-500">
                                    Nomor hio tersisa
                                </p>
                                <p className="mt-1 text-2xl font-semibold text-slate-900">
                                    {availability.incense_remaining}
                                </p>
                            </div>
                        </div>
                    </section>

                    {/* Ringkasan booking */}
                    <section>
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Booking
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Link
                                href="/admin/booking?status=PENDING"
                                className="rounded-[24px] border border-amber-200 bg-amber-50 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-amber-900">
                                    Booking masuk
                                </h3>
                                <p className="mt-2 text-3xl font-bold text-amber-700">
                                    {booking_counts.pending}
                                </p>
                                <p className="mt-1 text-sm text-amber-800/80">
                                    menunggu persetujuan
                                </p>
                            </Link>

                            <Link
                                href="/admin/booking?status=APPROVED"
                                className="rounded-[24px] border border-emerald-200 bg-emerald-50 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-emerald-900">
                                    Booking approve
                                </h3>
                                <p className="mt-2 text-3xl font-bold text-emerald-700">
                                    {booking_counts.approved}
                                </p>
                                <p className="mt-1 text-sm text-emerald-800/80">
                                    total disetujui
                                </p>
                            </Link>

                            <Link
                                href="/admin/booking?status=REJECTED"
                                className="rounded-[24px] border border-rose-200 bg-rose-50 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-rose-900">
                                    Booking reject
                                </h3>
                                <p className="mt-2 text-3xl font-bold text-rose-700">
                                    {booking_counts.rejected}
                                </p>
                                <p className="mt-1 text-sm text-rose-800/80">
                                    total ditolak
                                </p>
                            </Link>
                        </div>
                    </section>

                    {/* Pengaturan */}
                    <section>
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Pengaturan
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Link
                                href="/admin/paket"
                                className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Paket
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600">
                                    Atur harga, foto, dan tampil atau tidaknya
                                    paket.
                                </p>
                            </Link>

                            <Link
                                href="/admin/pembayaran"
                                className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Informasi pembayaran
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600">
                                    Atur nama bank, nama penerima, dan daftar
                                    nomor VA.
                                </p>
                            </Link>

                            <Link
                                href="/admin/booking/internal-perusahaan"
                                className="rounded-[24px] border border-sky-200 bg-sky-50 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-sky-950">
                                    Booking Internal Perusahaan
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-sky-900/80">
                                    Pakai meja dan hio khusus kantor, langsung
                                    jadi tanpa menunggu persetujuan.
                                </p>
                            </Link>

                            <Link
                                href="/admin/layout-meja"
                                className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Layout meja
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600">
                                    Lihat meja kosong, booking masuk, dan yang
                                    sudah disetujui.
                                </p>
                            </Link>

                            <Link
                                href="/admin/laporan"
                                className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Laporan
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600">
                                    Lihat check-in, keuangan, dan ringkasan
                                    agent.
                                </p>
                            </Link>

                            <Link
                                href="/admin/kertas-doa/marking"
                                className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Marking kertas doa
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600">
                                    Tandai posisi nama langsung di gambar
                                    template.
                                </p>
                            </Link>

                            <Link
                                href="/admin/kertas-doa/cek-cepat"
                                className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Cek cepat kertas
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600">
                                    Coba nama lalu langsung lihat dan download
                                    hasilnya.
                                </p>
                            </Link>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
