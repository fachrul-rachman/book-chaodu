import { Head, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Download,
    ImageOff,
    LoaderCircle,
    Pause,
    Play,
    RefreshCw,
    Ticket,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Album = {
    bookingNumber: string;
    eventName: string;
    eventDate: string;
    title: string;
    emptyStateText: string;
    wallpaperUrl: string | null;
};

type Media = {
    id: number;
    type: 'IMAGE' | 'VIDEO';
    scope: 'GLOBAL' | 'BOOKING';
    caption: string | null;
    width: number | null;
    height: number | null;
    previewUrl: string | null;
    viewerUrl: string;
    downloadUrl: string;
};

type ArchiveStatus =
    'IDLE' | 'PENDING' | 'PROCESSING' | 'READY' | 'FAILED' | 'EXPIRED';

type DownloadAll = {
    status: ArchiveStatus;
    totalSizeBytes: number;
    requestUrl: string;
    statusUrl: string;
    downloadUrl: string | null;
};

type PageProps = {
    album: Album;
    media: Media[];
    downloadAll: DownloadAll;
};

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} byte`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes / 1024;
    let unit = units[0];

    for (let index = 1; value >= 1024 && index < units.length; index++) {
        value /= 1024;
        unit = units[index];
    }

    return `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value)} ${unit}`;
}

function AlbumMediaCard({
    item,
    onOpen,
}: {
    item: Media;
    onOpen: (trigger: HTMLButtonElement) => void;
}) {
    const [loading, setLoading] = useState(item.previewUrl !== null);
    const [failed, setFailed] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const label =
        item.caption || (item.type === 'VIDEO' ? 'Video acara' : 'Foto acara');
    const isTallPortrait =
        item.width !== null &&
        item.height !== null &&
        item.height / item.width > 1.45;

    function retryPreview() {
        setFailed(false);
        setLoading(true);
        setAttempt((current) => current + 1);
    }

    const previewUrl = item.previewUrl
        ? `${item.previewUrl}${item.previewUrl.includes('?') ? '&' : '?'}attempt=${attempt}`
        : null;

    return (
        <article
            data-crop={isTallPortrait ? 'portrait' : 'natural'}
            className="group mb-3 break-inside-avoid overflow-hidden bg-stone-100 sm:mb-4"
        >
            <button
                type="button"
                aria-label={failed ? `Coba lagi ${label}` : `Buka ${label}`}
                onClick={(event) => {
                    if (failed) {
                        retryPreview();
                    } else {
                        onOpen(event.currentTarget);
                    }
                }}
                className={`relative block min-h-44 w-full overflow-hidden bg-stone-200 text-left focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#8a2d1f] ${isTallPortrait ? 'aspect-[4/5]' : ''}`}
            >
                {previewUrl && !failed ? (
                    <>
                        <img
                            src={previewUrl}
                            alt={label}
                            width={item.width ?? undefined}
                            height={item.height ?? undefined}
                            loading="lazy"
                            decoding="async"
                            onLoad={() => setLoading(false)}
                            onError={() => {
                                setLoading(false);
                                setFailed(true);
                            }}
                            className={`block w-full object-contain ${isTallPortrait ? 'h-full' : 'h-auto'} ${loading ? 'opacity-0' : 'opacity-100'}`}
                        />
                        {loading && (
                            <div className="absolute inset-0 flex items-center justify-center text-stone-500">
                                <LoaderCircle
                                    className="animate-spin"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">Memuat media</span>
                            </div>
                        )}
                        <span className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/5 to-transparent opacity-100 transition-opacity md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100" />
                        {item.type === 'VIDEO' && (
                            <span className="absolute top-1/2 left-1/2 flex size-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-black/55 text-white shadow-lg backdrop-blur-sm transition-transform group-hover:scale-105">
                                <Play
                                    className="ml-0.5"
                                    size={25}
                                    fill="currentColor"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">Putar video</span>
                            </span>
                        )}
                        <span className="absolute inset-x-0 bottom-0 px-4 py-3 text-sm leading-5 font-medium text-white opacity-100 transition-opacity md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100">
                            {label}
                        </span>
                    </>
                ) : (
                    <div className="flex min-h-56 w-full flex-col items-center justify-center gap-3 px-5 text-center text-stone-600">
                        <ImageOff size={30} aria-hidden="true" />
                        <p className="text-sm">Media belum dapat dimuat.</p>
                        {failed && (
                            <span className="inline-flex min-h-11 items-center gap-2 rounded-full border border-stone-300 bg-white px-4 text-sm font-semibold text-stone-800">
                                <RefreshCw size={16} aria-hidden="true" /> Coba
                                lagi
                            </span>
                        )}
                    </div>
                )}
            </button>
        </article>
    );
}

function DownloadAllControl({ initial }: { initial: DownloadAll }) {
    const [archive, setArchive] = useState(initial);
    const [requesting, setRequesting] = useState(false);

    useEffect(() => {
        if (archive.status !== 'PENDING' && archive.status !== 'PROCESSING') {
            return;
        }

        let cancelled = false;
        let timer: number;

        async function pollStatus() {
            try {
                const response = await fetch(archive.statusUrl, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('Status ZIP tidak dapat diperiksa.');
                }

                const payload = (await response.json()) as Partial<DownloadAll>;

                if (cancelled) {
                    return;
                }

                setArchive((current) => ({ ...current, ...payload }));

                if (
                    payload.status === 'PENDING' ||
                    payload.status === 'PROCESSING'
                ) {
                    timer = window.setTimeout(pollStatus, 2000);
                }
            } catch {
                if (!cancelled) {
                    setArchive((current) => ({ ...current, status: 'FAILED' }));
                }
            }
        }

        timer = window.setTimeout(pollStatus, 2000);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [archive.status, archive.statusUrl]);

    async function requestArchive() {
        setRequesting(true);
        setArchive((current) => ({ ...current, status: 'PENDING' }));

        try {
            const response = await fetch(archive.requestUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            if (!response.ok) {
                throw new Error('ZIP tidak dapat diminta.');
            }

            const payload = (await response.json()) as Partial<DownloadAll>;
            setArchive((current) => ({ ...current, ...payload }));
        } catch {
            setArchive((current) => ({ ...current, status: 'FAILED' }));
        } finally {
            setRequesting(false);
        }
    }

    const processing =
        archive.status === 'PENDING' || archive.status === 'PROCESSING';

    return (
        <div className="shrink-0" aria-live="polite">
            {archive.status === 'READY' && archive.downloadUrl ? (
                <a
                    href={archive.downloadUrl}
                    className="inline-flex min-h-11 items-center gap-2 px-2 text-sm font-semibold text-stone-800 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#8a2d1f] sm:px-3"
                >
                    <Download size={19} aria-hidden="true" /> Download ZIP
                </a>
            ) : processing ? (
                <span className="inline-flex min-h-11 items-center gap-2 px-2 text-sm font-semibold text-stone-600 sm:px-3">
                    <LoaderCircle
                        className="animate-spin"
                        size={19}
                        aria-hidden="true"
                    />
                    Sedang menyiapkan file ZIP…
                </span>
            ) : (
                <div>
                    {archive.status === 'FAILED' && (
                        <p className="sr-only">ZIP belum berhasil dibuat.</p>
                    )}
                    <button
                        type="button"
                        onClick={requestArchive}
                        disabled={requesting}
                        aria-label={
                            archive.status === 'FAILED'
                                ? 'Coba lagi'
                                : `Siapkan download semua, sekitar ${formatBytes(archive.totalSizeBytes)}`
                        }
                        className="inline-flex min-h-11 items-center gap-2 px-2 text-sm font-semibold text-stone-800 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#8a2d1f] disabled:opacity-60 sm:px-3"
                    >
                        <Download size={19} aria-hidden="true" />
                        {archive.status === 'FAILED'
                            ? 'Coba lagi'
                            : 'Download album'}
                    </button>
                </div>
            )}
        </div>
    );
}

function MediaViewer({
    media,
    index,
    setIndex,
    onClose,
    initialPlaying,
}: {
    media: Media[];
    index: number;
    setIndex: (index: number) => void;
    onClose: () => void;
    initialPlaying: boolean;
}) {
    const [playing, setPlaying] = useState(
        initialPlaying && media[index]?.type !== 'VIDEO',
    );
    const closeButton = useRef<HTMLButtonElement>(null);
    const dialog = useRef<HTMLDivElement>(null);
    const touchStartX = useRef<number | null>(null);
    const current = media[index];

    function show(nextIndex: number) {
        const normalized = (nextIndex + media.length) % media.length;
        setPlaying(false);
        setIndex(normalized);
    }

    useEffect(() => {
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        closeButton.current?.focus();

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, []);

    useEffect(() => {
        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();

                return;
            }

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                setPlaying(false);
                setIndex((index - 1 + media.length) % media.length);

                return;
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                setPlaying(false);
                setIndex((index + 1) % media.length);

                return;
            }

            if (event.key !== 'Tab' || !dialog.current) {
                return;
            }

            const focusable = Array.from(
                dialog.current.querySelectorAll<HTMLElement>(
                    'a[href], button:not([disabled]), video[controls], [tabindex]:not([tabindex="-1"])',
                ),
            );

            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [index, media.length, onClose, setIndex]);

    useEffect(() => {
        if (!playing || current.type === 'VIDEO') {
            return;
        }

        const timer = window.setTimeout(() => {
            const nextIndex = (index + 1) % media.length;
            setIndex(nextIndex);

            if (media[nextIndex].type === 'VIDEO') {
                setPlaying(false);
            }
        }, 4000);

        return () => window.clearTimeout(timer);
    }, [current.type, index, media, playing, setIndex]);

    function finishSwipe(clientX: number) {
        if (touchStartX.current === null) {
            return;
        }

        const distance = clientX - touchStartX.current;
        touchStartX.current = null;

        if (Math.abs(distance) < 50) {
            return;
        }

        show(distance < 0 ? index + 1 : index - 1);
    }

    const label =
        current.caption ||
        (current.type === 'VIDEO' ? 'Video acara' : 'Foto acara');

    return (
        <div
            ref={dialog}
            role="dialog"
            aria-modal="true"
            aria-label="Viewer media"
            onClick={(event) =>
                event.target === event.currentTarget && onClose()
            }
            onTouchStart={(event) => {
                touchStartX.current = event.touches[0]?.clientX ?? null;
            }}
            onTouchEnd={(event) => {
                const clientX = event.changedTouches[0]?.clientX;

                if (clientX !== undefined) {
                    finishSwipe(clientX);
                }
            }}
            className="fixed inset-0 z-50 flex flex-col bg-black/94 text-white"
        >
            <div className="flex min-h-14 items-center justify-between gap-3 border-b border-white/10 px-3 sm:px-5">
                <p
                    className="min-w-20 text-sm font-semibold"
                    aria-live="polite"
                >
                    {index + 1} dari {media.length}
                </p>
                <button
                    ref={closeButton}
                    type="button"
                    aria-label="Tutup viewer"
                    onClick={onClose}
                    className="inline-flex size-11 items-center justify-center rounded-full bg-white/12 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-white"
                >
                    <X size={24} aria-hidden="true" />
                </button>
            </div>

            <div className="relative flex min-h-0 flex-1 items-center justify-center px-2 pb-2 sm:px-20">
                <button
                    type="button"
                    aria-label="Media sebelumnya"
                    onClick={() => show(index - 1)}
                    className="absolute top-1/2 left-3 z-10 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 sm:left-5 sm:size-12"
                >
                    <ChevronLeft size={28} aria-hidden="true" />
                </button>

                {current.type === 'IMAGE' ? (
                    <img
                        key={current.id}
                        src={current.viewerUrl}
                        alt={label}
                        className="max-h-full max-w-full object-contain"
                    />
                ) : (
                    <video
                        key={current.id}
                        src={current.viewerUrl}
                        poster={current.previewUrl ?? undefined}
                        controls
                        playsInline
                        preload="metadata"
                        aria-label={`Pemutar video ${label}`}
                        className="max-h-full max-w-full"
                    />
                )}

                <button
                    type="button"
                    aria-label="Media berikutnya"
                    onClick={() => show(index + 1)}
                    className="absolute top-1/2 right-3 z-10 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/60 sm:right-5 sm:size-12"
                >
                    <ChevronRight size={28} aria-hidden="true" />
                </button>
            </div>

            <div className="border-t border-white/12 px-3 py-3 sm:px-5">
                <div className="mb-2 flex items-center justify-center gap-2">
                    <button
                        type="button"
                        aria-label={
                            playing ? 'Jeda slideshow' : 'Mulai slideshow'
                        }
                        onClick={() => setPlaying((value) => !value)}
                        disabled={current.type === 'VIDEO'}
                        className="inline-flex min-h-11 items-center gap-2 rounded-full border border-white/25 px-4 text-sm font-semibold disabled:opacity-40"
                    >
                        {playing ? (
                            <Pause size={18} aria-hidden="true" />
                        ) : (
                            <Play size={18} aria-hidden="true" />
                        )}
                        {playing ? 'Jeda' : 'Slideshow'}
                    </button>
                    <a
                        href={current.downloadUrl}
                        aria-label="Download media ini"
                        className="inline-flex min-h-11 items-center gap-2 rounded-full bg-white/12 px-4 text-sm font-semibold focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-white"
                    >
                        <Download size={19} aria-hidden="true" /> Download
                    </a>
                </div>
                <p className="mx-auto max-w-3xl text-center text-sm leading-5 text-white/90">
                    {label}
                </p>
            </div>
        </div>
    );
}

export default function PublicGalleryPage() {
    const { album, media, downloadAll } = usePage<PageProps>().props;
    const [viewerIndex, setViewerIndex] = useState<number | null>(null);
    const [viewerStartsPlaying, setViewerStartsPlaying] = useState(false);
    const viewerTrigger = useRef<HTMLButtonElement | null>(null);

    function openViewer(
        index: number,
        trigger: HTMLButtonElement,
        startsPlaying = false,
    ) {
        viewerTrigger.current = trigger;
        setViewerStartsPlaying(startsPlaying);
        setViewerIndex(index);
    }

    function closeViewer() {
        viewerTrigger.current?.focus();
        setViewerIndex(null);
    }

    return (
        <>
            <Head title={album.title}>
                <meta name="robots" content="noindex,nofollow,noarchive" />
                <meta name="googlebot" content="noindex,nofollow,noarchive" />
            </Head>

            <main className="min-h-screen bg-[#f4efe7] text-stone-900">
                <header className="sticky top-0 z-40 border-b border-stone-200/80 bg-white/92 backdrop-blur-md">
                    <div className="mx-auto flex min-h-[58px] max-w-[1600px] items-center justify-between gap-3 px-4 sm:px-6">
                        <p className="shrink-0 text-sm font-bold tracking-[0.18em] text-[#74291f] sm:text-base">
                            CHAO DU
                        </p>
                        <nav
                            aria-label="Pilihan album"
                            className="flex min-w-0 items-center divide-x divide-stone-200"
                        >
                            <button
                                type="button"
                                aria-label="Putar slideshow album"
                                disabled={media.length === 0}
                                onClick={(event) =>
                                    openViewer(0, event.currentTarget, true)
                                }
                                className="inline-flex min-h-11 items-center gap-2 px-2 text-sm font-semibold text-stone-800 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#8a2d1f] disabled:opacity-40 sm:px-3"
                            >
                                <Play size={18} aria-hidden="true" />
                                <span className="hidden sm:inline">
                                    Slideshow
                                </span>
                            </button>
                            {media.length > 0 && (
                                <DownloadAllControl initial={downloadAll} />
                            )}
                            <span className="inline-flex min-h-11 items-center px-2 text-xs font-semibold whitespace-nowrap text-stone-500 sm:px-3 sm:text-sm">
                                {media.length} media
                            </span>
                        </nav>
                    </div>
                </header>

                <section className="relative isolate overflow-hidden bg-[#6f241b] text-white">
                    {album.wallpaperUrl ? (
                        <img
                            src={album.wallpaperUrl}
                            alt=""
                            aria-hidden="true"
                            className="absolute inset-0 -z-20 size-full object-cover"
                        />
                    ) : null}
                    <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_15%_20%,rgba(255,221,164,0.18),transparent_34%),linear-gradient(115deg,rgba(49,14,11,0.94),rgba(111,36,27,0.63),rgba(30,20,16,0.35))]" />
                    <div className="absolute inset-x-0 bottom-0 -z-10 h-32 bg-gradient-to-t from-black/40 to-transparent" />
                    <div className="mx-auto flex min-h-[280px] max-w-[1600px] flex-col items-center justify-center px-5 py-10 text-center sm:min-h-[380px] sm:px-8 sm:py-12">
                        <p className="text-sm font-semibold tracking-[0.16em] text-amber-100 uppercase">
                            {album.eventName}
                        </p>
                        <h1 className="mt-3 max-w-4xl text-3xl leading-tight font-semibold text-balance sm:text-4xl">
                            {album.title}
                        </h1>
                        <div className="mt-7 flex flex-wrap justify-center gap-3 text-sm sm:gap-4">
                            <span className="inline-flex min-h-11 items-center gap-2 rounded-full bg-white/12 px-4 backdrop-blur-sm">
                                <CalendarDays size={18} aria-hidden="true" />
                                {album.eventDate}
                            </span>
                            <span className="inline-flex min-h-11 items-center gap-2 rounded-full bg-white/12 px-4 font-semibold backdrop-blur-sm">
                                <Ticket size={18} aria-hidden="true" />
                                {album.bookingNumber}
                            </span>
                        </div>
                    </div>
                </section>

                <section
                    className="mx-auto max-w-[1600px] px-3 py-8 sm:px-5 sm:py-10"
                    aria-labelledby="album-media-heading"
                >
                    <div className="mb-6 flex items-end justify-between gap-4">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.14em] text-[#8a2d1f] uppercase">
                                Dokumentasi acara
                            </p>
                            <h2
                                id="album-media-heading"
                                className="mt-1 text-2xl font-semibold text-stone-900"
                            >
                                Foto dan video
                            </h2>
                        </div>
                        {media.length > 0 && (
                            <p className="shrink-0 text-sm font-medium text-stone-600">
                                {media.length} media
                            </p>
                        )}
                    </div>

                    {media.length === 0 ? (
                        <div className="rounded-[28px] border border-dashed border-stone-300 bg-white px-6 py-16 text-center shadow-sm">
                            <ImageOff
                                className="mx-auto text-stone-400"
                                size={38}
                                aria-hidden="true"
                            />
                            <h3 className="mt-4 text-lg font-semibold text-stone-900">
                                {album.emptyStateText}
                            </h3>
                            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-stone-600">
                                Silakan buka kembali halaman ini setelah tim
                                dokumentasi mengunggah foto atau video.
                            </p>
                        </div>
                    ) : (
                        <div
                            data-testid="album-masonry"
                            data-layout="masonry"
                            className="columns-1 gap-3 min-[520px]:columns-2 sm:gap-4 lg:columns-3 xl:columns-4"
                        >
                            {media.map((item, index) => (
                                <AlbumMediaCard
                                    key={item.id}
                                    item={item}
                                    onOpen={(trigger) =>
                                        openViewer(index, trigger)
                                    }
                                />
                            ))}
                        </div>
                    )}
                </section>

                <footer className="border-t border-stone-200 bg-white/70 px-5 py-7 text-center text-sm text-stone-600">
                    Simpan link album ini untuk melihat dokumentasi terbaru.
                </footer>
            </main>

            {viewerIndex !== null && media[viewerIndex] && (
                <MediaViewer
                    media={media}
                    index={viewerIndex}
                    setIndex={setViewerIndex}
                    onClose={closeViewer}
                    initialPlaying={viewerStartsPlaying}
                />
            )}
        </>
    );
}
