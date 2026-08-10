import { Head, Link, usePage } from '@inertiajs/react';
import type { Auth } from '@/types';

export default function ContentTeamDashboard() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const user = auth.user!;

    return (
        <>
            <Head title="Team Content" />

            <main className="min-h-screen bg-[var(--color-bg,#f8fafc)] px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-5xl space-y-8">
                    <section className="rounded-[24px] border border-[var(--color-border)] bg-[var(--color-panel)] p-6 shadow-sm sm:p-8">
                        <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">
                                    Dashboard Team Content
                                </p>
                                <h1 className="mt-1 text-2xl font-semibold text-slate-900 sm:text-3xl">
                                    Selamat datang, {user.name}
                                </h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                                    Kelola dokumentasi acara dan media customer
                                    dari halaman ini.
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

                    <section aria-labelledby="gallery-work-heading">
                        <h2
                            id="gallery-work-heading"
                            className="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase"
                        >
                            Pilih area kerja
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <article className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Media Global
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600">
                                    Foto dan video acara yang akan tampil di
                                    seluruh album customer.
                                </p>
                                <p className="mt-5 text-sm font-medium text-slate-500">
                                    Tersedia pada modul upload berikutnya.
                                </p>
                            </article>

                            <article className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                                <h3 className="text-lg font-semibold text-slate-900">
                                    Media Customer
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600">
                                    Cari nomor booking lalu kelola foto dan
                                    video khusus customer.
                                </p>
                                <p className="mt-5 text-sm font-medium text-slate-500">
                                    Tersedia pada modul upload berikutnya.
                                </p>
                            </article>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
