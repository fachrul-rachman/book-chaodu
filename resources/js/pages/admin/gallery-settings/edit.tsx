import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, Image as ImageIcon, Save, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

type Props = {
    settings: {
        event_name: string;
        event_date: string;
        album_title: string;
        empty_state_text: string;
        wallpaper_url: string | null;
    };
    wallpaper_max_megabytes: number;
    wallpaper_width: number;
    wallpaper_height: number;
    flash?: { status?: string | null };
};

export default function GallerySettingsPage() {
    const {
        settings,
        wallpaper_max_megabytes,
        wallpaper_width,
        wallpaper_height,
        flash,
    } = usePage<Props>().props;
    const [localWallpaperPreview, setLocalWallpaperPreview] = useState<
        string | null
    >(null);
    const localWallpaperUrl = useRef<string | null>(null);
    const form = useForm<{
        event_name: string;
        event_date: string;
        album_title: string;
        empty_state_text: string;
        wallpaper: File | null;
    }>({
        event_name: settings.event_name,
        event_date: settings.event_date,
        album_title: settings.album_title,
        empty_state_text: settings.empty_state_text,
        wallpaper: null,
    });

    useEffect(
        () => () => {
            if (localWallpaperUrl.current) {
                URL.revokeObjectURL(localWallpaperUrl.current);
            }
        },
        [],
    );

    const wallpaperPreview = localWallpaperPreview ?? settings.wallpaper_url;

    function selectWallpaper(file: File | null) {
        if (localWallpaperUrl.current) {
            URL.revokeObjectURL(localWallpaperUrl.current);
            localWallpaperUrl.current = null;
        }

        form.setData('wallpaper', file);

        if (file) {
            localWallpaperUrl.current = URL.createObjectURL(file);
        }

        setLocalWallpaperPreview(localWallpaperUrl.current);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/admin/galeri', {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Pengaturan galeri" />

            <main className="min-h-screen bg-slate-50 px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-6xl space-y-6">
                    <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.14em] text-[#8a2d1f] uppercase">
                                Pengaturan satu acara
                            </p>
                            <h1 className="mt-2 text-3xl font-semibold text-slate-950">
                                Identitas album galeri
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                                Perubahan berlaku untuk seluruh link album,
                                termasuk booking yang sudah ada.
                            </p>
                        </div>
                        <Link
                            href="/admin"
                            className="inline-flex min-h-12 items-center justify-center rounded-full border border-[#8a2d1f] px-5 text-sm font-semibold text-[#8a2d1f] focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#8a2d1f]"
                        >
                            Kembali ke dashboard
                        </Link>
                    </header>

                    {flash?.status ? (
                        <div
                            role="status"
                            className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900"
                        >
                            {flash.status}
                        </div>
                    ) : null}

                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.85fr)] lg:items-start">
                        <form
                            onSubmit={submit}
                            className="space-y-5 rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-7"
                        >
                            <TextField
                                id="event_name"
                                label="Nama acara"
                                value={form.data.event_name}
                                error={form.errors.event_name}
                                maxLength={120}
                                onChange={(value) =>
                                    form.setData('event_name', value)
                                }
                            />

                            <label className="block" htmlFor="event_date">
                                <span className="mb-2 block text-sm font-semibold text-slate-800">
                                    Tanggal acara
                                </span>
                                <input
                                    id="event_date"
                                    type="date"
                                    required
                                    value={form.data.event_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'event_date',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.event_date,
                                    )}
                                    aria-describedby={
                                        form.errors.event_date
                                            ? 'event_date-error'
                                            : undefined
                                    }
                                    className="min-h-12 w-full rounded-2xl border border-slate-300 bg-white px-4 text-base text-slate-950 focus:border-[#8a2d1f] focus:ring-3 focus:ring-[#8a2d1f]/15 focus:outline-none"
                                />
                                <FieldError
                                    id="event_date-error"
                                    message={form.errors.event_date}
                                />
                            </label>

                            <TextField
                                id="album_title"
                                label="Judul album"
                                value={form.data.album_title}
                                error={form.errors.album_title}
                                maxLength={160}
                                onChange={(value) =>
                                    form.setData('album_title', value)
                                }
                            />

                            <label className="block" htmlFor="empty_state_text">
                                <span className="mb-2 block text-sm font-semibold text-slate-800">
                                    Teks saat album masih kosong
                                </span>
                                <textarea
                                    id="empty_state_text"
                                    required
                                    rows={3}
                                    maxLength={240}
                                    value={form.data.empty_state_text}
                                    onChange={(event) =>
                                        form.setData(
                                            'empty_state_text',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.empty_state_text,
                                    )}
                                    aria-describedby={
                                        form.errors.empty_state_text
                                            ? 'empty_state_text-error'
                                            : undefined
                                    }
                                    className="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-base leading-6 text-slate-950 focus:border-[#8a2d1f] focus:ring-3 focus:ring-[#8a2d1f]/15 focus:outline-none"
                                />
                                <FieldError
                                    id="empty_state_text-error"
                                    message={form.errors.empty_state_text}
                                />
                            </label>

                            <label className="block" htmlFor="wallpaper">
                                <span className="mb-2 block text-sm font-semibold text-slate-800">
                                    Wallpaper album
                                </span>
                                <span className="flex min-h-14 cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-slate-400 bg-slate-50 px-4 text-sm font-semibold text-slate-800 hover:bg-slate-100">
                                    <Upload size={20} aria-hidden="true" />
                                    {form.data.wallpaper?.name ??
                                        'Pilih gambar pengganti'}
                                </span>
                                <input
                                    id="wallpaper"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(event) =>
                                        selectWallpaper(
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                    aria-describedby="wallpaper-help wallpaper-error"
                                    className="sr-only"
                                />
                                <p
                                    id="wallpaper-help"
                                    className="mt-2 text-sm leading-6 text-slate-600"
                                >
                                    Ukuran wajib {wallpaper_width} x{' '}
                                    {wallpaper_height} piksel. JPG, PNG, atau
                                    WebP, maksimal {wallpaper_max_megabytes} MB.
                                    Jika tidak memilih file, wallpaper lama
                                    tetap dipakai.
                                </p>
                                <FieldError
                                    id="wallpaper-error"
                                    message={form.errors.wallpaper}
                                />
                            </label>

                            <button
                                type="submit"
                                disabled={form.processing}
                                className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-[#8a2d1f] px-6 text-sm font-semibold text-white focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#8a2d1f] disabled:cursor-wait disabled:opacity-60 sm:w-auto"
                            >
                                <Save size={19} aria-hidden="true" />
                                {form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan pengaturan'}
                            </button>
                        </form>

                        <aside className="lg:sticky lg:top-6">
                            <p className="mb-3 text-sm font-semibold text-slate-700">
                                Pratinjau kepala album
                            </p>
                            <div className="overflow-hidden rounded-[30px] bg-[#6f241b] text-white shadow-xl shadow-stone-900/15">
                                <div
                                    className="aspect-[12/5] bg-stone-800"
                                    data-aspect-ratio="12/5"
                                >
                                    {wallpaperPreview ? (
                                        <img
                                            src={wallpaperPreview}
                                            alt="Pratinjau wallpaper album"
                                            className="size-full object-contain"
                                        />
                                    ) : (
                                        <div className="flex size-full items-center justify-center text-white/40">
                                            <ImageIcon
                                                size={64}
                                                aria-hidden="true"
                                            />
                                        </div>
                                    )}
                                </div>
                                <div className="border-t border-white/10 bg-gradient-to-br from-[#4d1712] to-[#76291f] p-7">
                                    <p className="text-xs font-semibold tracking-[0.16em] text-amber-100 uppercase">
                                        {form.data.event_name || 'Nama acara'}
                                    </p>
                                    <h2 className="mt-3 text-3xl leading-tight font-semibold text-balance">
                                        {form.data.album_title || 'Judul album'}
                                    </h2>
                                    <p className="mt-6 inline-flex min-h-11 w-fit items-center gap-2 rounded-full bg-white/15 px-4 text-sm backdrop-blur-sm">
                                        <CalendarDays
                                            size={18}
                                            aria-hidden="true"
                                        />
                                        {form.data.event_date ||
                                            'Tanggal acara'}
                                    </p>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </main>
        </>
    );
}

function TextField({
    id,
    label,
    value,
    error,
    maxLength,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    maxLength: number;
    onChange: (value: string) => void;
}) {
    return (
        <label className="block" htmlFor={id}>
            <span className="mb-2 block text-sm font-semibold text-slate-800">
                {label}
            </span>
            <input
                id={id}
                type="text"
                required
                maxLength={maxLength}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                className="min-h-12 w-full rounded-2xl border border-slate-300 bg-white px-4 text-base text-slate-950 focus:border-[#8a2d1f] focus:ring-3 focus:ring-[#8a2d1f]/15 focus:outline-none"
            />
            <FieldError id={`${id}-error`} message={error} />
        </label>
    );
}

function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p
            id={id}
            role="alert"
            className="mt-2 text-sm font-medium text-red-700"
        >
            {message}
        </p>
    ) : null;
}
