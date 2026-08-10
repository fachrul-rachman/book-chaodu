import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Eye,
    EyeOff,
    Film,
    Flame,
    GripVertical,
    Image as ImageIcon,
    MapPin,
    RefreshCw,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

type BookingSummary = {
    id: number;
    bookingNumber: string;
    customerName: string;
    packageName: string;
    tableNumber: string | null;
    incenseNumber: string | null;
    mediaCount: number;
};

type Media = {
    id: number;
    uuid: string;
    type: 'IMAGE' | 'VIDEO';
    status: 'PROCESSING' | 'READY' | 'FAILED' | 'HIDDEN';
    filename: string;
    mimeType: string;
    sizeBytes: number;
    caption: string | null;
    sortOrder: number | null;
    previewUrl: string | null;
    createdAt: string;
    errorMessage: string | null;
};

type QueueItem = {
    key: string;
    file: File;
    progress: number;
    status: 'waiting' | 'uploading' | 'processing' | 'failed' | 'done';
    message: string;
    mediaId?: number;
    previewUrl?: string;
};

type PageProps = {
    results: BookingSummary[];
    selectedBooking: BookingSummary | null;
    media: Media[];
    filters: { q: string; table: string; incense: string };
    limits: { photoMb: number; videoMb: number; captionCharacters: number };
};

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

async function api<T>(url: string, method: string, body?: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const payload = (await response.json().catch(() => ({}))) as Record<
        string,
        unknown
    >;

    if (!response.ok) {
        const errors = payload.errors as Record<string, string[]> | undefined;
        const message = errors
            ? Object.values(errors).flat()[0]
            : payload.message;

        throw new Error(
            typeof message === 'string'
                ? message
                : 'Permintaan tidak berhasil.',
        );
    }

    return payload as T;
}

function putFile(
    url: string,
    file: Blob,
    headers: Record<string, string>,
    onProgress: (loaded: number) => void,
): Promise<string> {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('PUT', url);
        Object.entries(headers).forEach(([name, value]) =>
            request.setRequestHeader(name, value),
        );
        request.upload.onprogress = (event) =>
            event.lengthComputable && onProgress(event.loaded);
        request.onerror = () => reject(new Error('Koneksi upload terputus.'));
        request.onload = () => {
            if (request.status >= 200 && request.status < 300) {
                resolve(request.getResponseHeader('ETag') ?? '');
            } else {
                reject(
                    new Error(
                        `Upload ditolak oleh penyimpanan (${request.status}).`,
                    ),
                );
            }
        };
        request.send(file);
    });
}

function formatSize(bytes: number): string {
    if (bytes >= 1024 ** 3) {
        return `${(bytes / 1024 ** 3).toFixed(1)} GB`;
    }

    if (bytes >= 1024 ** 2) {
        return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    }

    return `${Math.ceil(bytes / 1024)} KB`;
}

export default function CustomerMediaPage() {
    const props = usePage<PageProps>().props;

    return (
        <CustomerMediaWorkspace
            key={props.selectedBooking?.id ?? 'search'}
            {...props}
        />
    );
}

function CustomerMediaWorkspace(props: PageProps) {
    const [query, setQuery] = useState(props.filters.q);
    const [tableQuery, setTableQuery] = useState(props.filters.table);
    const [incenseQuery, setIncenseQuery] = useState(props.filters.incense);
    const [media, setMedia] = useState(props.media);
    const [queue, setQueue] = useState<QueueItem[]>([]);
    const [draggedId, setDraggedId] = useState<number | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const booking = props.selectedBooking;

    useEffect(() => {
        if (!media.some((item) => item.status === 'PROCESSING')) {
            return;
        }

        const timer = window.setInterval(
            () =>
                router.reload({
                    only: ['media'],
                    onSuccess: (page) =>
                        setMedia((page.props as unknown as PageProps).media),
                }),
            3000,
        );

        return () => window.clearInterval(timer);
    }, [media]);

    function searchBookings(event: FormEvent) {
        event.preventDefault();
        router.get('/content/media/customer', {
            q: query.trim(),
            table: tableQuery.trim(),
            incense: incenseQuery.trim(),
        });
    }

    function selectBookingUrl(bookingId: number): string {
        const params = new URLSearchParams({ booking: String(bookingId) });

        if (props.filters.q) {
            params.set('q', props.filters.q);
        }

        if (props.filters.table) {
            params.set('table', props.filters.table);
        }

        if (props.filters.incense) {
            params.set('incense', props.filters.incense);
        }

        return `/content/media/customer?${params.toString()}`;
    }

    const updateQueue = (key: string, values: Partial<QueueItem>) =>
        setQueue((current) =>
            current.map((item) =>
                item.key === key ? { ...item, ...values } : item,
            ),
        );

    async function uploadFile(item: QueueItem) {
        if (!booking) {
            return;
        }

        let mediaId: number | undefined;

        try {
            updateQueue(item.key, {
                status: 'uploading',
                message: 'Menyiapkan upload…',
            });
            const initiated = await api<{
                media: { id: number };
                upload:
                    | {
                          mode: 'single';
                          url: string;
                          headers: Record<string, string>;
                      }
                    | { mode: 'multipart'; partSize: number };
            }>(`/content/media/customer/${booking.id}/uploads`, 'POST', {
                upload_token: item.key,
                original_filename: item.file.name,
                mime_type: item.file.type,
                size_bytes: item.file.size,
            });
            mediaId = initiated.media.id;
            updateQueue(item.key, { mediaId });

            const parts: { part_number: number; etag: string }[] = [];

            if (initiated.upload.mode === 'single') {
                await putFile(
                    initiated.upload.url,
                    item.file,
                    initiated.upload.headers,
                    (loaded) =>
                        updateQueue(item.key, {
                            progress: Math.round(
                                (loaded / item.file.size) * 100,
                            ),
                            message: 'Mengunggah…',
                        }),
                );
            } else {
                const partSize = initiated.upload.partSize;
                const partCount = Math.ceil(item.file.size / partSize);

                for (let index = 0; index < partCount; index += 1) {
                    const start = index * partSize;
                    const blob = item.file.slice(
                        start,
                        Math.min(start + partSize, item.file.size),
                    );
                    const signed = await api<{
                        url: string;
                        headers: Record<string, string>;
                    }>(
                        `/content/media/customer/${booking.id}/${mediaId}/parts`,
                        'POST',
                        { part_number: index + 1 },
                    );
                    let etag = '';

                    for (let attempt = 1; attempt <= 3; attempt += 1) {
                        try {
                            etag = await putFile(
                                signed.url,
                                blob,
                                signed.headers,
                                (loaded) =>
                                    updateQueue(item.key, {
                                        progress: Math.round(
                                            ((start + loaded) /
                                                item.file.size) *
                                                100,
                                        ),
                                        message: `Mengunggah bagian ${index + 1} dari ${partCount}…`,
                                    }),
                            );
                            break;
                        } catch (error) {
                            if (attempt === 3) {
                                throw error;
                            }
                        }
                    }

                    if (!etag) {
                        throw new Error(
                            'R2 tidak mengembalikan ETag. Periksa CORS bucket.',
                        );
                    }

                    parts.push({ part_number: index + 1, etag });
                }
            }

            updateQueue(item.key, {
                progress: 100,
                status: 'processing',
                message: 'Memeriksa file…',
            });
            await api(
                `/content/media/customer/${booking.id}/${mediaId}/complete`,
                'POST',
                { parts },
            );
            updateQueue(item.key, { status: 'done', message: 'Selesai' });
            window.setTimeout(
                () =>
                    setQueue((current) =>
                        current.filter((queued) => queued.key !== item.key),
                    ),
                1500,
            );
            router.reload({
                only: ['media', 'selectedBooking'],
                onSuccess: (page) =>
                    setMedia((page.props as unknown as PageProps).media),
            });
        } catch (error) {
            updateQueue(item.key, {
                status: 'failed',
                message:
                    error instanceof Error ? error.message : 'Upload gagal.',
                mediaId,
            });
        }
    }

    async function startFiles(files: File[]) {
        const items = files.map((file) => ({
            key: crypto.randomUUID(),
            file,
            previewUrl: file.type.startsWith('image/')
                ? URL.createObjectURL(file)
                : undefined,
            progress: 0,
            status: 'waiting' as const,
            message: 'Menunggu…',
        }));
        setQueue((current) => [...current, ...items]);
        let next = 0;

        await Promise.all(
            Array.from({ length: Math.min(3, items.length) }, async () => {
                while (next < items.length) {
                    const item = items[next];
                    next += 1;
                    await uploadFile(item);
                }
            }),
        );
    }

    async function retry(item: QueueItem) {
        if (booking && item.mediaId) {
            await api(
                `/content/media/customer/${booking.id}/${item.mediaId}`,
                'DELETE',
            ).catch(() => undefined);
        }

        const reset = {
            ...item,
            progress: 0,
            status: 'waiting' as const,
            message: 'Mencoba lagi…',
            mediaId: undefined,
        };
        updateQueue(item.key, reset);
        await uploadFile(reset);
    }

    async function remove(item: Media) {
        if (
            !booking ||
            !window.confirm(
                `Hapus permanen “${item.filename}” dari booking ${booking.bookingNumber}?`,
            )
        ) {
            return;
        }

        await api(`/content/media/customer/${booking.id}/${item.id}`, 'DELETE');
        setMedia((current) => current.filter((value) => value.id !== item.id));
    }

    async function saveCaption(item: Media, caption: string) {
        if (!booking || (item.caption ?? '') === caption.trim()) {
            return;
        }

        await api(`/content/media/customer/${booking.id}/${item.id}`, 'PATCH', {
            caption: caption.trim() || null,
        });
        setMedia((current) =>
            current.map((value) =>
                value.id === item.id
                    ? { ...value, caption: caption.trim() || null }
                    : value,
            ),
        );
    }

    async function toggleVisibility(item: Media) {
        if (!booking) {
            return;
        }

        const status = item.status === 'HIDDEN' ? 'READY' : 'HIDDEN';
        const result = await api<{ media: Media }>(
            `/content/media/customer/${booking.id}/${item.id}/status`,
            'PATCH',
            { status },
        );
        setMedia((current) =>
            current.map((value) =>
                value.id === item.id ? result.media : value,
            ),
        );
    }

    async function setOrder(nextMedia: Media[]) {
        if (!booking) {
            return;
        }

        const previous = media;
        setMedia(nextMedia);

        try {
            await api(`/content/media/customer/${booking.id}/order`, 'PUT', {
                media_ids: nextMedia.map((item) => item.id),
            });
        } catch (error) {
            setMedia(previous);
            window.alert(
                error instanceof Error
                    ? error.message
                    : 'Urutan gagal disimpan.',
            );
        }
    }

    function move(id: number, direction: -1 | 1) {
        const index = media.findIndex((item) => item.id === id);
        const target = index + direction;

        if (index < 0 || target < 0 || target >= media.length) {
            return;
        }

        const next = [...media];
        [next[index], next[target]] = [next[target], next[index]];
        void setOrder(next);
    }

    function drop(targetId: number) {
        if (draggedId === null || draggedId === targetId) {
            return;
        }

        const source = media.findIndex((item) => item.id === draggedId);
        const target = media.findIndex((item) => item.id === targetId);

        if (source < 0 || target < 0) {
            return;
        }

        const next = [...media];
        const [dragged] = next.splice(source, 1);
        next.splice(target, 0, dragged);
        setDraggedId(null);
        void setOrder(next);
    }

    return (
        <>
            <Head title="Media Customer" />
            <main className="min-h-screen bg-[var(--color-bg,#f8fafc)] px-4 py-6 sm:px-6 sm:py-8">
                <div className="mx-auto max-w-6xl space-y-6">
                    <header className="flex items-start gap-3">
                        <Link
                            href="/content"
                            aria-label="Kembali ke dashboard"
                            className="mt-1 inline-flex size-11 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white"
                        >
                            <ArrowLeft size={20} />
                        </Link>
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                Team Content
                            </p>
                            <h1 className="text-2xl font-semibold text-slate-950 sm:text-3xl">
                                Media Customer
                            </h1>
                            <p className="mt-1 text-sm text-slate-600">
                                Cari dan pastikan booking sebelum mengunggah
                                dokumentasi khusus customer.
                            </p>
                        </div>
                    </header>

                    <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <form
                            onSubmit={searchBookings}
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto]"
                        >
                            <label className="text-sm font-semibold text-slate-700">
                                Nama customer atau kode booking
                                <div className="relative mt-1">
                                    <Search
                                        className="absolute top-1/2 left-4 -translate-y-1/2 text-slate-400"
                                        size={19}
                                    />
                                    <input
                                        value={query}
                                        onChange={(event) =>
                                            setQuery(event.target.value)
                                        }
                                        minLength={2}
                                        maxLength={100}
                                        className="min-h-12 w-full rounded-2xl border border-slate-300 pr-4 pl-11 text-base"
                                        placeholder="Contoh: CD-001 atau Budi"
                                    />
                                </div>
                            </label>
                            <label className="text-sm font-semibold text-slate-700">
                                Nomor meja
                                <div className="relative mt-1">
                                    <MapPin
                                        className="absolute top-1/2 left-4 -translate-y-1/2 text-slate-400"
                                        size={19}
                                    />
                                    <input
                                        value={tableQuery}
                                        onChange={(event) =>
                                            setTableQuery(
                                                event.target.value
                                                    .toUpperCase()
                                                    .replace(/\s/g, ''),
                                            )
                                        }
                                        maxLength={20}
                                        className="min-h-12 w-full rounded-2xl border border-slate-300 pr-4 pl-11 text-base"
                                        placeholder="Contoh: A18"
                                    />
                                </div>
                            </label>
                            <label className="text-sm font-semibold text-slate-700">
                                Nomor hio
                                <div className="relative mt-1">
                                    <Flame
                                        className="absolute top-1/2 left-4 -translate-y-1/2 text-slate-400"
                                        size={19}
                                    />
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        value={incenseQuery}
                                        onChange={(event) =>
                                            setIncenseQuery(
                                                event.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        maxLength={3}
                                        className="min-h-12 w-full rounded-2xl border border-slate-300 pr-4 pl-11 text-base"
                                        placeholder="Contoh: 12"
                                    />
                                </div>
                            </label>
                            <button
                                type="submit"
                                className="mt-auto min-h-12 rounded-full bg-[var(--color-brand)] px-6 text-sm font-semibold text-white"
                            >
                                Cari booking
                            </button>
                        </form>

                        {(props.filters.q ||
                            props.filters.table ||
                            props.filters.incense) && (
                            <div className="mt-5 space-y-3">
                                {props.results.length === 0 ? (
                                    <p className="rounded-2xl bg-slate-50 p-5 text-sm text-slate-600">
                                        Booking approved tidak ditemukan.
                                    </p>
                                ) : (
                                    props.results.map((result) => (
                                        <article
                                            key={result.id}
                                            className="flex flex-col gap-4 rounded-2xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div>
                                                <p className="font-semibold text-slate-950">
                                                    {result.bookingNumber}
                                                </p>
                                                <p className="text-sm text-slate-700">
                                                    {result.customerName} ·{' '}
                                                    {result.packageName}
                                                </p>
                                                <p className="mt-1 text-sm text-slate-500">
                                                    {result.tableNumber
                                                        ? `Meja ${result.tableNumber}`
                                                        : 'Tanpa meja'}{' '}
                                                    ·{' '}
                                                    {result.incenseNumber
                                                        ? `Hio ${result.incenseNumber}`
                                                        : 'Tanpa hio'}{' '}
                                                    · {result.mediaCount} media
                                                </p>
                                            </div>
                                            <Link
                                                href={selectBookingUrl(
                                                    result.id,
                                                )}
                                                aria-label={`Pilih booking ${result.bookingNumber}`}
                                                className="inline-flex min-h-11 items-center justify-center rounded-full border border-[var(--color-brand)] px-5 text-sm font-semibold text-[var(--color-brand)]"
                                            >
                                                Pilih booking
                                            </Link>
                                        </article>
                                    ))
                                )}
                            </div>
                        )}
                    </section>

                    {booking && (
                        <>
                            <section className="rounded-3xl border-2 border-[var(--color-brand)] bg-white p-5 shadow-sm sm:p-6">
                                <p className="text-xs font-semibold tracking-wide text-[var(--color-brand)] uppercase">
                                    Booking terpilih
                                </p>
                                <div className="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <h2 className="text-xl font-semibold text-slate-950">
                                            {booking.bookingNumber} ·{' '}
                                            {booking.customerName}
                                        </h2>
                                        <p className="mt-1 text-sm text-slate-600">
                                            {booking.packageName}
                                        </p>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {booking.tableNumber && (
                                                <span className="inline-flex min-h-9 items-center gap-2 rounded-full bg-amber-50 px-3 text-sm font-semibold text-amber-900">
                                                    <MapPin size={16} /> Meja{' '}
                                                    {booking.tableNumber}
                                                </span>
                                            )}
                                            {booking.incenseNumber && (
                                                <span className="inline-flex min-h-9 items-center rounded-full bg-red-50 px-3 text-sm font-semibold text-red-900">
                                                    Hio {booking.incenseNumber}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <input
                                        ref={fileInput}
                                        type="file"
                                        multiple
                                        accept="image/jpeg,image/png,image/webp,video/mp4"
                                        className="sr-only"
                                        aria-label="Pilih foto atau video customer"
                                        onChange={(event) => {
                                            void startFiles(
                                                Array.from(
                                                    event.target.files ?? [],
                                                ),
                                            );
                                            event.target.value = '';
                                        }}
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            fileInput.current?.click()
                                        }
                                        className="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-[var(--color-brand)] px-6 text-sm font-semibold text-white"
                                    >
                                        <Upload size={19} /> Upload ke booking
                                        ini
                                    </button>
                                </div>
                                <p className="mt-4 text-xs text-slate-500">
                                    Foto maksimal {props.limits.photoMb} MB.
                                    Video MP4 maksimal {props.limits.videoMb}{' '}
                                    MB.
                                </p>

                                {queue.length > 0 && (
                                    <div
                                        className="mt-5 space-y-3"
                                        aria-live="polite"
                                    >
                                        {queue.map((item) => (
                                            <div
                                                key={item.key}
                                                className="rounded-2xl bg-slate-50 p-3"
                                            >
                                                <div className="flex items-start justify-between gap-3 text-sm">
                                                    <div className="flex min-w-0 items-center gap-3">
                                                        {item.previewUrl ? (
                                                            <img
                                                                src={
                                                                    item.previewUrl
                                                                }
                                                                alt=""
                                                                className="size-12 rounded-lg object-cover"
                                                            />
                                                        ) : (
                                                            <Film className="text-slate-400" />
                                                        )}
                                                        <div className="min-w-0">
                                                            <p className="truncate font-medium">
                                                                {item.file.name}
                                                            </p>
                                                            <p
                                                                className={
                                                                    item.status ===
                                                                    'failed'
                                                                        ? 'text-red-700'
                                                                        : 'text-slate-500'
                                                                }
                                                            >
                                                                {item.message}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    {item.status ===
                                                        'failed' && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                void retry(item)
                                                            }
                                                            className="inline-flex min-h-10 items-center gap-1 rounded-full border px-3 font-semibold"
                                                        >
                                                            <RefreshCw
                                                                size={16}
                                                            />{' '}
                                                            Coba lagi
                                                        </button>
                                                    )}
                                                </div>
                                                {item.status !== 'failed' && (
                                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                                                        <div
                                                            className="h-full rounded-full bg-[var(--color-brand)]"
                                                            style={{
                                                                width: `${item.progress}%`,
                                                            }}
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </section>

                            <section aria-labelledby="customer-album-title">
                                <div className="mb-3 flex items-end justify-between">
                                    <div>
                                        <h2
                                            id="customer-album-title"
                                            className="text-lg font-semibold text-slate-900"
                                        >
                                            Media booking{' '}
                                            {booking.bookingNumber}
                                        </h2>
                                        <p className="text-sm text-slate-500">
                                            Media ini hanya untuk album customer
                                            terpilih.
                                        </p>
                                    </div>
                                    <span className="text-sm font-medium text-slate-500">
                                        {media.length} media
                                    </span>
                                </div>
                                {media.length === 0 ? (
                                    <div className="rounded-3xl border border-dashed border-slate-300 bg-white py-14 text-center">
                                        <ImageIcon
                                            className="mx-auto text-slate-400"
                                            size={34}
                                        />
                                        <p className="mt-3 font-medium text-slate-700">
                                            Belum ada media customer
                                        </p>
                                    </div>
                                ) : (
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {media.map((item, index) => (
                                            <article
                                                key={item.id}
                                                draggable
                                                onDragStart={() =>
                                                    setDraggedId(item.id)
                                                }
                                                onDragOver={(event) =>
                                                    event.preventDefault()
                                                }
                                                onDrop={() => drop(item.id)}
                                                className={`group overflow-hidden rounded-3xl border bg-white shadow-sm ${item.status === 'HIDDEN' ? 'border-amber-300 opacity-75' : 'border-slate-200'}`}
                                            >
                                                <div className="relative aspect-[4/3] bg-slate-100">
                                                    {item.type === 'IMAGE' &&
                                                    item.previewUrl ? (
                                                        <img
                                                            src={
                                                                item.previewUrl
                                                            }
                                                            alt={
                                                                item.caption ||
                                                                item.filename
                                                            }
                                                            className="size-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex size-full flex-col items-center justify-center gap-2 text-slate-500">
                                                            {item.type ===
                                                            'VIDEO' ? (
                                                                <Film
                                                                    size={38}
                                                                />
                                                            ) : (
                                                                <ImageIcon
                                                                    size={38}
                                                                />
                                                            )}
                                                            <span className="text-sm">
                                                                {item.status ===
                                                                'FAILED'
                                                                    ? 'Upload gagal'
                                                                    : item.status ===
                                                                        'PROCESSING'
                                                                      ? 'Sedang diproses'
                                                                      : 'Pratinjau tidak tersedia'}
                                                            </span>
                                                        </div>
                                                    )}
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            void remove(item)
                                                        }
                                                        aria-label={`Hapus ${item.filename}`}
                                                        className="absolute top-3 right-3 inline-flex size-11 items-center justify-center rounded-full bg-slate-950/75 text-white opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100"
                                                    >
                                                        <Trash2 size={18} />
                                                    </button>
                                                    <span className="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full bg-white/90 px-2 py-1 text-xs font-semibold">
                                                        <GripVertical
                                                            size={14}
                                                        />{' '}
                                                        {item.type === 'VIDEO'
                                                            ? 'Video'
                                                            : 'Foto'}
                                                    </span>
                                                </div>
                                                <div className="space-y-3 p-4">
                                                    <div>
                                                        <p className="truncate text-sm font-semibold">
                                                            {item.filename}
                                                        </p>
                                                        <p className="text-xs text-slate-500">
                                                            {formatSize(
                                                                item.sizeBytes,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <label className="block text-xs font-semibold text-slate-600">
                                                        Caption (opsional)
                                                        <input
                                                            defaultValue={
                                                                item.caption ??
                                                                ''
                                                            }
                                                            maxLength={
                                                                props.limits
                                                                    .captionCharacters
                                                            }
                                                            onBlur={(event) =>
                                                                void saveCaption(
                                                                    item,
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm font-normal"
                                                        />
                                                    </label>
                                                    <div className="flex justify-between gap-2">
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                ![
                                                                    'READY',
                                                                    'HIDDEN',
                                                                ].includes(
                                                                    item.status,
                                                                )
                                                            }
                                                            onClick={() =>
                                                                void toggleVisibility(
                                                                    item,
                                                                )
                                                            }
                                                            className="inline-flex min-h-11 items-center gap-2 rounded-full border px-3 text-xs font-semibold disabled:opacity-40"
                                                        >
                                                            {item.status ===
                                                            'HIDDEN' ? (
                                                                <>
                                                                    <Eye
                                                                        size={
                                                                            16
                                                                        }
                                                                    />{' '}
                                                                    Tampilkan
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <EyeOff
                                                                        size={
                                                                            16
                                                                        }
                                                                    />{' '}
                                                                    Sembunyikan
                                                                </>
                                                            )}
                                                        </button>
                                                        <div className="flex gap-2">
                                                            <button
                                                                type="button"
                                                                aria-label={`Naikkan ${item.filename}`}
                                                                disabled={
                                                                    index === 0
                                                                }
                                                                onClick={() =>
                                                                    move(
                                                                        item.id,
                                                                        -1,
                                                                    )
                                                                }
                                                                className="inline-flex size-11 items-center justify-center rounded-full border disabled:opacity-30"
                                                            >
                                                                <ArrowUp
                                                                    size={18}
                                                                />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                aria-label={`Turunkan ${item.filename}`}
                                                                disabled={
                                                                    index ===
                                                                    media.length -
                                                                        1
                                                                }
                                                                onClick={() =>
                                                                    move(
                                                                        item.id,
                                                                        1,
                                                                    )
                                                                }
                                                                className="inline-flex size-11 items-center justify-center rounded-full border disabled:opacity-30"
                                                            >
                                                                <ArrowDown
                                                                    size={18}
                                                                />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </article>
                                        ))}
                                    </div>
                                )}
                            </section>
                        </>
                    )}
                </div>
            </main>
        </>
    );
}
