import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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
    can_manage_text_settings: boolean;
    text_settings: {
        prayer: {
            vertical: {
                font_scale: number;
                line_height: number;
                column_gap_scale: number;
            };
            rotated: {
                font_scale: number;
            };
        };
        incense: {
            vertical: {
                font_scale: number;
                line_height: number;
                column_gap_scale: number;
            };
            horizontal: {
                font_scale: number;
                line_height: number;
            };
        };
    };
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
    const {
        paper_type,
        base_url,
        back_url,
        can_manage_text_settings,
        text_settings,
        types,
        inputs,
        previews,
    } = usePage<Props>().props;
    const [form, setForm] = useState({
        type: paper_type,
        ...inputs,
    });
    const settingsForm = useForm({
        prayer: {
            vertical: {
                font_scale: String(text_settings.prayer.vertical.font_scale),
                line_height: String(text_settings.prayer.vertical.line_height),
                column_gap_scale: String(
                    text_settings.prayer.vertical.column_gap_scale,
                ),
            },
            rotated: {
                font_scale: String(text_settings.prayer.rotated.font_scale),
            },
        },
        incense: {
            vertical: {
                font_scale: String(text_settings.incense.vertical.font_scale),
                line_height: String(text_settings.incense.vertical.line_height),
                column_gap_scale: String(
                    text_settings.incense.vertical.column_gap_scale,
                ),
            },
            horizontal: {
                font_scale: String(text_settings.incense.horizontal.font_scale),
                line_height: String(
                    text_settings.incense.horizontal.line_height,
                ),
            },
        },
    });

    const isPrayerPaper = useMemo(() => form.type === 'A', [form.type]);
    const layoutClass = can_manage_text_settings
        ? 'grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)_320px]'
        : 'grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]';

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

    const saveTextSettings = () => {
        settingsForm.transform((data) => ({
            prayer: {
                vertical: {
                    font_scale: Number(data.prayer.vertical.font_scale),
                    line_height: Number(data.prayer.vertical.line_height),
                    column_gap_scale: Number(
                        data.prayer.vertical.column_gap_scale,
                    ),
                },
                rotated: {
                    font_scale: Number(data.prayer.rotated.font_scale),
                },
            },
            incense: {
                vertical: {
                    font_scale: Number(data.incense.vertical.font_scale),
                    line_height: Number(data.incense.vertical.line_height),
                    column_gap_scale: Number(
                        data.incense.vertical.column_gap_scale,
                    ),
                },
                horizontal: {
                    font_scale: Number(data.incense.horizontal.font_scale),
                    line_height: Number(data.incense.horizontal.line_height),
                },
            },
        })).put('/admin/kertas-doa/cek-cepat/pengaturan-tulisan', {
            preserveScroll: true,
        });
    };

    const updateTextSetting = (
        group: 'prayer' | 'incense',
        style: 'vertical' | 'rotated' | 'horizontal',
        field: 'font_scale' | 'line_height' | 'column_gap_scale',
        value: string,
    ) => {
        settingsForm.setData((current) => ({
            ...current,
            [group]: {
                ...current[group],
                [style]: {
                    ...current[group][style],
                    [field]: value,
                },
            },
        }));
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

                    <div className={layoutClass}>
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
                                            <textarea
                                                rows={3}
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
                                            <textarea
                                                rows={3}
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

                        {can_manage_text_settings ? (
                            <aside className="xl:sticky xl:top-8 xl:self-start">
                                <section className="space-y-4 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">
                                            Ukuran tulisan
                                        </p>
                                        <p className="mt-1 text-sm leading-6 text-slate-700">
                                            `1.00` normal. `0.90` lebih kecil.
                                            `1.10` lebih besar.
                                        </p>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="rounded-2xl border border-[var(--color-border)] bg-slate-50 p-4">
                                            <p className="text-sm font-semibold text-slate-900">
                                                Kertas Doa
                                            </p>
                                            <div className="mt-3 grid gap-3">
                                                <label className="block">
                                                    <span className="mb-2 block text-sm">
                                                        Mandarin tegak
                                                    </span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={
                                                            settingsForm.data
                                                                .prayer.vertical
                                                                .font_scale
                                                        }
                                                        onChange={(event) =>
                                                            updateTextSetting(
                                                                'prayer',
                                                                'vertical',
                                                                'font_scale',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                    />
                                                </label>
                                                <label className="block">
                                                    <span className="mb-2 block text-sm">
                                                        Jarak baris Mandarin
                                                    </span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={
                                                            settingsForm.data
                                                                .prayer.vertical
                                                                .line_height
                                                        }
                                                        onChange={(event) =>
                                                            updateTextSetting(
                                                                'prayer',
                                                                'vertical',
                                                                'line_height',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                    />
                                                </label>
                                                <label className="block">
                                                    <span className="mb-2 block text-sm">
                                                        Jarak kolom Mandarin
                                                    </span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={
                                                            settingsForm.data
                                                                .prayer.vertical
                                                                .column_gap_scale
                                                        }
                                                        onChange={(event) =>
                                                            updateTextSetting(
                                                                'prayer',
                                                                'vertical',
                                                                'column_gap_scale',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                    />
                                                </label>
                                                <label className="block">
                                                    <span className="mb-2 block text-sm">
                                                        Indonesia putar 90 derajat
                                                    </span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={
                                                            settingsForm.data
                                                                .prayer.rotated
                                                                .font_scale
                                                        }
                                                        onChange={(event) =>
                                                            updateTextSetting(
                                                                'prayer',
                                                                'rotated',
                                                                'font_scale',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                    />
                                                </label>
                                            </div>
                                        </div>

                                        <div className="rounded-2xl border border-[var(--color-border)] bg-slate-50 p-4">
                                            <p className="text-sm font-semibold text-slate-900">
                                                Kertas Hio
                                            </p>
                                            <div className="mt-3 grid gap-3">
                                                <label className="block">
                                                    <span className="mb-2 block text-sm">
                                                        Mandarin tegak
                                                    </span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={
                                                            settingsForm.data
                                                                .incense
                                                                .vertical
                                                                .font_scale
                                                        }
                                                        onChange={(event) =>
                                                            updateTextSetting(
                                                                'incense',
                                                                'vertical',
                                                                'font_scale',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                    />
                                                </label>
                                                <label className="block">
                                                    <span className="mb-2 block text-sm">
                                                        Jarak baris Mandarin
                                                    </span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={
                                                            settingsForm.data
                                                                .incense
                                                                .vertical
                                                                .line_height
                                                        }
                                                        onChange={(event) =>
                                                            updateTextSetting(
                                                                'incense',
                                                                'vertical',
                                                                'line_height',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                    />
                                                </label>
                                                <label className="block">
                                                    <span className="mb-2 block text-sm">
                                                        Jarak kolom Mandarin
                                                    </span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={
                                                            settingsForm.data
                                                                .incense
                                                                .vertical
                                                                .column_gap_scale
                                                        }
                                                        onChange={(event) =>
                                                            updateTextSetting(
                                                                'incense',
                                                                'vertical',
                                                                'column_gap_scale',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                    />
                                                </label>
                                                <label className="block">
                                                    <span className="mb-2 block text-sm">
                                                        Indonesia mendatar
                                                    </span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={
                                                            settingsForm.data
                                                                .incense
                                                                .horizontal
                                                                .font_scale
                                                        }
                                                        onChange={(event) =>
                                                            updateTextSetting(
                                                                'incense',
                                                                'horizontal',
                                                                'font_scale',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                    />
                                                </label>
                                                <label className="block">
                                                    <span className="mb-2 block text-sm">
                                                        Jarak baris Indonesia
                                                    </span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={
                                                            settingsForm.data
                                                                .incense
                                                                .horizontal
                                                                .line_height
                                                        }
                                                        onChange={(event) =>
                                                            updateTextSetting(
                                                                'incense',
                                                                'horizontal',
                                                                'line_height',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                                    />
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    {Object.values(settingsForm.errors).length >
                                    0 ? (
                                        <div className="space-y-1 text-sm text-red-700">
                                            {Object.values(
                                                settingsForm.errors,
                                            ).map((error) => (
                                                <p key={error}>{error}</p>
                                            ))}
                                        </div>
                                    ) : null}

                                    <button
                                        type="button"
                                        onClick={saveTextSettings}
                                        disabled={settingsForm.processing}
                                        className="w-full rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60"
                                    >
                                        {settingsForm.processing
                                            ? 'Menyimpan...'
                                            : 'Simpan ukuran tulisan'}
                                    </button>
                                </section>
                            </aside>
                        ) : null}
                    </div>
                </div>
            </main>
        </>
    );
}
