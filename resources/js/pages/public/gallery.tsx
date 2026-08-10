import { Head, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Film,
    ImageOff,
    LoaderCircle,
    RefreshCw,
    Ticket,
} from 'lucide-react';
import { useState } from 'react';

type Album = {
    bookingNumber: string;
    eventName: string;
    eventDate: string;
    title: string;
};

type Media = {
    id: number;
    type: 'IMAGE' | 'VIDEO';
    scope: 'GLOBAL' | 'BOOKING';
    caption: string | null;
    width: number | null;
    height: number | null;
    previewUrl: string | null;
};

type PageProps = {
    album: Album;
    media: Media[];
};

function AlbumMediaCard({ item }: { item: Media }) {
    const [loading, setLoading] = useState(item.previewUrl !== null);
    const [failed, setFailed] = useState(false);
    const [attempt, setAttempt] = useState(0);
    const label =
        item.caption || (item.type === 'VIDEO' ? 'Video acara' : 'Foto acara');

    function retryPreview() {
        setFailed(false);
        setLoading(true);
        setAttempt((current) => current + 1);
    }

    const previewUrl = item.previewUrl
        ? `${item.previewUrl}${item.previewUrl.includes('?') ? '&' : '?'}attempt=${attempt}`
        : null;

    return (
        <article className="group overflow-hidden rounded-[22px] border border-stone-200 bg-white shadow-[0_10px_30px_rgba(73,52,37,0.08)]">
            <div className="relative aspect-[4/3] overflow-hidden bg-stone-100">
                {item.type === 'IMAGE' && previewUrl && !failed ? (
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
                            className={`size-full object-cover transition duration-300 ${loading ? 'opacity-0' : 'opacity-100'}`}
                        />
                        {loading && (
                            <div className="absolute inset-0 flex items-center justify-center text-stone-500">
                                <LoaderCircle
                                    className="animate-spin"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">Memuat foto</span>
                            </div>
                        )}
                    </>
                ) : item.type === 'VIDEO' ? (
                    <div className="flex size-full flex-col items-center justify-center gap-3 bg-gradient-to-br from-stone-800 to-stone-950 text-white">
                        <span className="flex size-14 items-center justify-center rounded-full bg-white/12">
                            <Film size={28} aria-hidden="true" />
                        </span>
                        <span className="text-sm font-semibold tracking-wide">
                            Video
                        </span>
                    </div>
                ) : (
                    <div className="flex size-full flex-col items-center justify-center gap-3 px-5 text-center text-stone-600">
                        <ImageOff size={30} aria-hidden="true" />
                        <p className="text-sm">Foto belum dapat dimuat.</p>
                        <button
                            type="button"
                            onClick={retryPreview}
                            className="inline-flex min-h-11 items-center gap-2 rounded-full border border-stone-300 bg-white px-4 text-sm font-semibold text-stone-800"
                        >
                            <RefreshCw size={16} aria-hidden="true" /> Coba lagi
                        </button>
                    </div>
                )}
            </div>
            <div className="min-h-16 px-4 py-4">
                <p className="text-sm leading-6 font-medium text-stone-800">
                    {label}
                </p>
            </div>
        </article>
    );
}

export default function PublicGalleryPage() {
    const { album, media } = usePage<PageProps>().props;

    return (
        <>
            <Head title={album.title}>
                <meta name="robots" content="noindex,nofollow,noarchive" />
                <meta name="googlebot" content="noindex,nofollow,noarchive" />
            </Head>

            <main className="min-h-screen bg-[#f4efe7] text-stone-900">
                <header className="relative isolate overflow-hidden bg-[#6f241b] text-white">
                    <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_15%_20%,rgba(255,221,164,0.24),transparent_34%),linear-gradient(130deg,rgba(72,18,14,0.94),rgba(138,45,31,0.82))]" />
                    <div className="absolute inset-x-0 bottom-0 -z-10 h-24 bg-gradient-to-t from-black/15 to-transparent" />
                    <div className="mx-auto flex min-h-[360px] max-w-6xl flex-col justify-end px-5 py-10 sm:min-h-[420px] sm:px-8 sm:py-14">
                        <p className="text-sm font-semibold tracking-[0.16em] text-amber-100 uppercase">
                            {album.eventName}
                        </p>
                        <h1 className="mt-3 max-w-3xl text-3xl leading-tight font-semibold text-balance sm:text-5xl">
                            {album.title}
                        </h1>
                        <div className="mt-7 flex flex-col gap-3 text-sm sm:flex-row sm:flex-wrap sm:gap-4">
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
                </header>

                <section
                    className="mx-auto max-w-6xl px-4 py-9 sm:px-8 sm:py-12"
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
                                Dokumentasi acara belum tersedia.
                            </h3>
                            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-stone-600">
                                Silakan buka kembali halaman ini setelah tim
                                dokumentasi mengunggah foto atau video.
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 min-[480px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {media.map((item) => (
                                <AlbumMediaCard key={item.id} item={item} />
                            ))}
                        </div>
                    )}
                </section>

                <footer className="border-t border-stone-200 bg-white/70 px-5 py-7 text-center text-sm text-stone-600">
                    Simpan link album ini untuk melihat dokumentasi terbaru.
                </footer>
            </main>
        </>
    );
}
