import { Head, Link } from '@inertiajs/react';

export default function Home() {
    return (
        <>
            <Head title="Beranda" />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-5xl flex-col justify-between gap-8">
                    <section className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                        <div className="rounded-[24px] border border-[var(--color-border)] bg-[var(--color-panel)] p-6 shadow-sm sm:p-8">
                            <p className="text-sm font-medium tracking-[0.2em] text-[var(--color-brand)] uppercase">
                                Chao Du Booking
                            </p>
                            <h1 className="mt-4 text-3xl font-semibold text-[var(--color-ink)] sm:text-5xl">
                                Layanan pemesanan untuk kebutuhan acara Chao Du.
                            </h1>
                            <p className="mt-4 max-w-2xl text-base leading-7 text-slate-700 sm:text-lg">
                                Silakan masuk untuk melanjutkan pekerjaan Anda.
                            </p>
                            <div className="mt-8">
                                <Link
                                    href="/masuk"
                                    className="inline-flex rounded-full bg-[var(--color-brand)] px-5 py-3 text-center text-sm font-semibold text-white"
                                >
                                    Masuk
                                </Link>
                            </div>
                        </div>

                        <div className="rounded-[24px] border border-[var(--color-border)] bg-white/80 p-6 shadow-sm sm:p-8">
                            <h2 className="text-lg font-semibold">Ringkas</h2>
                            <ul className="mt-5 space-y-4 text-sm leading-6 text-slate-700">
                                <li>
                                    Pemakaian dibuat sederhana dan nyaman di
                                    ponsel.
                                </li>
                                <li>
                                    Data penting hanya bisa diakses oleh petugas
                                    yang berwenang.
                                </li>
                                <li>
                                    Halaman ini bisa dipasang seperti aplikasi
                                    di perangkat yang mendukung.
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
