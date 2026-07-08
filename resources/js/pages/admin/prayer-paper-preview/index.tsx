import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type PreviewRow = {
    label: string;
    image_url: string;
    download_url: string;
};

type Props = {
    paper_type: 'A' | 'B';
    base_url: string;
    back_url: string;
    types: Array<{
        value: 'A' | 'B';
        label: string;
    }>;
    inputs: {
        name_1_indonesian: string;
        name_1_mandarin: string;
        name_2_indonesian: string;
        name_2_mandarin: string;
        incense_indonesian: string;
        incense_mandarin: string;
    };
    previews: PreviewRow[];
};

export default function PrayerPaperPreviewPage() {
    const { paper_type, base_url, back_url, types, inputs, previews } =
        usePage<Props>().props;
    const [form, setForm] = useState({
        type: paper_type,
        ...inputs,
    });

    const isPrayerPaper = useMemo(() => form.type === 'A', [form.type]);

    const submit = () => {
        router.get(
            base_url,
            {
                type: form.type,
                name_1_indonesian: form.name_1_indonesian,
                name_1_mandarin: form.name_1_mandarin,
                name_2_indonesian: form.name_2_indonesian,
                name_2_mandarin: form.name_2_mandarin,
                incense_indonesian: form.incense_indonesian,
                incense_mandarin: form.incense_mandarin,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const reset = () => {
        setForm({
            type: form.type,
            name_1_indonesian: '',
            name_1_mandarin: '',
            name_2_indonesian: '',
            name_2_mandarin: '',
            incense_indonesian: '',
            incense_mandarin: '',
        });
        router.get(
            base_url,
            { type: form.type },
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    };

    return (
        <>
            <Head title="Cek Cepat Kertas Doa" />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-6xl space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold">
                                Cek cepat kertas
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                Isi nama, lihat hasil, lalu langsung download.
                            </p>
                        </div>

                        <Link
                            href={back_url}
                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
                        <section className="space-y-5 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                            <div className="space-y-3">
                                <p className="text-sm font-semibold text-slate-900">
                                    Pilih jenis
                                </p>
                                <div className="flex flex-wrap gap-3">
                                    {types.map((item) => (
                                        <button
                                            key={item.value}
                                            type="button"
                                            onClick={() =>
                                                setForm((current) => ({
                                                    ...current,
                                                    type: item.value,
                                                }))
                                            }
                                            className={`rounded-full px-4 py-2 text-sm font-semibold ${
                                                form.type === item.value
                                                    ? 'bg-[var(--color-brand)] text-white'
                                                    : 'border border-[var(--color-border)] text-slate-700'
                                            }`}
                                        >
                                            {item.label}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {isPrayerPaper ? (
                                <div className="space-y-4">
                                    <div className="rounded-2xl border border-[var(--color-border)] p-4">
                                        <p className="text-sm font-semibold text-slate-900">
                                            Nama 1
                                        </p>
                                        <div className="mt-3 space-y-3">
                                            <label className="block">
                                                <span className="mb-2 block text-sm">
                                                    Nama Indonesia
                                                </span>
                                                <input
                                                    type="text"
                                                    value={
                                                        form.name_1_indonesian
                                                    }
                                                    onChange={(event) =>
                                                        setForm((current) => ({
                                                            ...current,
                                                            name_1_indonesian:
                                                                event.target
                                                                    .value,
                                                        }))
                                                    }
                                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                />
                                            </label>
                                            <label className="block">
                                                <span className="mb-2 block text-sm">
                                                    Nama Mandarin
                                                </span>
                                                <input
                                                    type="text"
                                                    value={form.name_1_mandarin}
                                                    onChange={(event) =>
                                                        setForm((current) => ({
                                                            ...current,
                                                            name_1_mandarin:
                                                                event.target
                                                                    .value,
                                                        }))
                                                    }
                                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-[var(--color-border)] p-4">
                                        <p className="text-sm font-semibold text-slate-900">
                                            Nama 2
                                        </p>
                                        <div className="mt-3 space-y-3">
                                            <label className="block">
                                                <span className="mb-2 block text-sm">
                                                    Nama Indonesia
                                                </span>
                                                <input
                                                    type="text"
                                                    value={
                                                        form.name_2_indonesian
                                                    }
                                                    onChange={(event) =>
                                                        setForm((current) => ({
                                                            ...current,
                                                            name_2_indonesian:
                                                                event.target
                                                                    .value,
                                                        }))
                                                    }
                                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                />
                                            </label>
                                            <label className="block">
                                                <span className="mb-2 block text-sm">
                                                    Nama Mandarin
                                                </span>
                                                <input
                                                    type="text"
                                                    value={form.name_2_mandarin}
                                                    onChange={(event) =>
                                                        setForm((current) => ({
                                                            ...current,
                                                            name_2_mandarin:
                                                                event.target
                                                                    .value,
                                                        }))
                                                    }
                                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                />
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-2xl border border-[var(--color-border)] p-4">
                                    <p className="text-sm font-semibold text-slate-900">
                                        Nama orang atau keluarga yang ingin
                                        didoakan
                                    </p>
                                    <div className="mt-3 space-y-3">
                                        <label className="block">
                                            <span className="mb-2 block text-sm">
                                                Nama Indonesia
                                            </span>
                                            <input
                                                type="text"
                                                value={
                                                    form.incense_indonesian
                                                }
                                                onChange={(event) =>
                                                    setForm((current) => ({
                                                        ...current,
                                                        incense_indonesian:
                                                            event.target.value,
                                                    }))
                                                }
                                                className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                            />
                                        </label>
                                        <label className="block">
                                            <span className="mb-2 block text-sm">
                                                Nama Mandarin
                                            </span>
                                            <input
                                                type="text"
                                                value={form.incense_mandarin}
                                                onChange={(event) =>
                                                    setForm((current) => ({
                                                        ...current,
                                                        incense_mandarin:
                                                            event.target.value,
                                                    }))
                                                }
                                                className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                            />
                                        </label>
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    onClick={submit}
                                    className="rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white"
                                >
                                    Lihat hasil
                                </button>
                                <button
                                    type="button"
                                    onClick={reset}
                                    className="rounded-full border border-[var(--color-border)] px-5 py-3 text-sm font-semibold text-slate-700"
                                >
                                    Kosongkan
                                </button>
                            </div>
                        </section>

                        <section className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                            {previews.length > 0 ? (
                                <div className="grid gap-6 xl:grid-cols-2">
                                    {previews.map((preview) => (
                                        <article
                                            key={preview.download_url}
                                            className="space-y-4 rounded-[20px] border border-[var(--color-border)] bg-slate-50 p-4"
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <h2 className="text-sm font-semibold text-slate-900">
                                                    {preview.label}
                                                </h2>
                                                <a
                                                    href={preview.download_url}
                                                    className="rounded-full bg-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-white"
                                                >
                                                    Download
                                                </a>
                                            </div>

                                            <div className="overflow-auto rounded-2xl border border-[var(--color-border)] bg-white p-4">
                                                <img
                                                    src={preview.image_url}
                                                    alt={preview.label}
                                                    className="mx-auto h-auto max-h-[70vh] w-auto max-w-full"
                                                />
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            ) : (
                                <div className="flex min-h-[420px] items-center justify-center rounded-[20px] border border-dashed border-[var(--color-border)] bg-[var(--color-panel)] px-6 text-center text-sm leading-6 text-slate-700">
                                    Isi nama dulu, lalu klik lihat hasil.
                                </div>
                            )}
                        </section>
                    </div>
                </div>
            </main>
        </>
    );
}
