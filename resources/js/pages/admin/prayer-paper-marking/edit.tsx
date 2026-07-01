import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent, MouseEvent } from 'react';
import { useMemo, useRef, useState } from 'react';

type Marker = {
    x: number;
    y: number;
    width: number;
    height: number;
};

type MarkerKey = 'single' | 'left' | 'right';
type DragMode = 'draw' | 'move';

type DragState = {
    key: MarkerKey;
    mode: DragMode;
    startPointer: {
        x: number;
        y: number;
    };
    startMarker: Marker;
};

type Props = {
    types: Array<{
        value: 'A' | 'B';
        label: string;
    }>;
    marking_type: 'A' | 'B';
    marking: {
        image_url: string | null;
        canvas_width: number;
        canvas_height: number;
        markers: {
            single: Marker;
            left: Marker;
            right: Marker;
        };
        has_image: boolean;
        storage_disk: string;
        title: string;
        show_three_markers: boolean;
    };
    flash?: {
        status?: string | null;
    };
};

const markerLabels: Record<MarkerKey, string> = {
    single: 'Satu nama',
    left: 'Dua nama kiri',
    right: 'Dua nama kanan',
};

const markerColors: Record<MarkerKey, string> = {
    single: '#8a2d1f',
    left: '#166534',
    right: '#1d4ed8',
};

export default function PrayerPaperMarkingPage() {
    const { marking, flash, types, marking_type } = usePage<Props>().props;
    const visibleMarkerKeys = (
        marking.show_three_markers ? ['single', 'left', 'right'] : ['single']
    ) as MarkerKey[];
    const [activeMarker, setActiveMarker] = useState<MarkerKey>('single');
    const [imageSize, setImageSize] = useState({
        width: marking.canvas_width,
        height: marking.canvas_height,
    });
    const [dragState, setDragState] = useState<DragState | null>(null);
    const imageRef = useRef<HTMLImageElement | null>(null);
    const form = useForm({
        type: marking_type,
        canvas_width: String(marking.canvas_width),
        canvas_height: String(marking.canvas_height),
        markers: {
            single: { ...marking.markers.single },
            left: { ...marking.markers.left },
            right: { ...marking.markers.right },
        },
        template_image: null as File | null,
    });

    const scale = useMemo(() => {
        return {
            x: imageSize.width / Number(form.data.canvas_width || 1),
            y: imageSize.height / Number(form.data.canvas_height || 1),
        };
    }, [
        form.data.canvas_height,
        form.data.canvas_width,
        imageSize.height,
        imageSize.width,
    ]);

    const pointerToCanvas = (event: MouseEvent<HTMLDivElement>) => {
        const image = imageRef.current;

        if (!image) {
            return null;
        }

        const rect = image.getBoundingClientRect();
        const x =
            ((event.clientX - rect.left) / rect.width) *
            Number(form.data.canvas_width || 1);
        const y =
            ((event.clientY - rect.top) / rect.height) *
            Number(form.data.canvas_height || 1);

        return {
            x: Math.max(0, x),
            y: Math.max(0, y),
        };
    };

    const clampMarker = (marker: Marker): Marker => {
        const canvasWidth = Number(form.data.canvas_width || 1);
        const canvasHeight = Number(form.data.canvas_height || 1);
        const width = Math.min(Math.max(1, marker.width), canvasWidth);
        const height = Math.min(Math.max(1, marker.height), canvasHeight);

        return {
            x: Math.min(
                Math.max(0, marker.x),
                Math.max(0, canvasWidth - width),
            ),
            y: Math.min(
                Math.max(0, marker.y),
                Math.max(0, canvasHeight - height),
            ),
            width,
            height,
        };
    };

    const updateMarker = (key: MarkerKey, marker: Marker) => {
        form.setData('markers', {
            ...form.data.markers,
            [key]: clampMarker(marker),
        });
    };

    const onMouseDown = (event: MouseEvent<HTMLDivElement>) => {
        const point = pointerToCanvas(event);

        if (!point) {
            return;
        }

        setDragState({
            key: activeMarker,
            mode: 'draw',
            startPointer: point,
            startMarker: {
                x: point.x,
                y: point.y,
                width: 1,
                height: 1,
            },
        });

        updateMarker(activeMarker, {
            x: point.x,
            y: point.y,
            width: 1,
            height: 1,
        });
    };

    const startMoveMarker = (
        event: MouseEvent<HTMLDivElement>,
        key: MarkerKey,
    ) => {
        event.stopPropagation();

        const point = pointerToCanvas(event);

        if (!point) {
            return;
        }

        setActiveMarker(key);
        setDragState({
            key,
            mode: 'move',
            startPointer: point,
            startMarker: { ...form.data.markers[key] },
        });
    };

    const onMouseMove = (event: MouseEvent<HTMLDivElement>) => {
        if (!dragState) {
            return;
        }

        const point = pointerToCanvas(event);

        if (!point) {
            return;
        }

        if (dragState.mode === 'draw') {
            updateMarker(dragState.key, {
                x: Math.min(dragState.startPointer.x, point.x),
                y: Math.min(dragState.startPointer.y, point.y),
                width: Math.max(
                    1,
                    Math.abs(point.x - dragState.startPointer.x),
                ),
                height: Math.max(
                    1,
                    Math.abs(point.y - dragState.startPointer.y),
                ),
            });

            return;
        }

        updateMarker(dragState.key, {
            x: dragState.startMarker.x + (point.x - dragState.startPointer.x),
            y: dragState.startMarker.y + (point.y - dragState.startPointer.y),
            width: dragState.startMarker.width,
            height: dragState.startMarker.height,
        });
    };

    const onMouseUp = () => {
        setDragState(null);
    };

    const submit = () => {
        form.transform((data) => ({
            ...data,
            canvas_width: Number(data.canvas_width),
            canvas_height: Number(data.canvas_height),
        }));

        form.put('/admin/kertas-doa/marking', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const onImageChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('template_image', file);
    };

    const clearMarker = (key: MarkerKey) => {
        updateMarker(key, {
            x: 0,
            y: 0,
            width: 1,
            height: 1,
        });
    };

    const updateMarkerField = (
        key: MarkerKey,
        field: keyof Marker,
        value: string,
    ) => {
        const nextValue = Number(value);

        updateMarker(key, {
            ...form.data.markers[key],
            [field]: Number.isFinite(nextValue) ? nextValue : 0,
        });
    };

    return (
        <>
            <Head title={`Marking ${marking.title.toLowerCase()}`} />

            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-6xl space-y-6">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-semibold">
                                Marking {marking.title.toLowerCase()}
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                Upload gambar template, lalu tarik atau geser
                                area nama yang mau dipakai.
                            </p>
                        </div>

                        <Link
                            href="/admin"
                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        {types.map((type) => (
                            <Link
                                key={type.value}
                                href={`/admin/kertas-doa/marking?type=${type.value}`}
                                className={`rounded-full px-4 py-2 text-sm font-semibold ${
                                    marking_type === type.value
                                        ? 'bg-[var(--color-brand)] text-white'
                                        : 'border border-[var(--color-border)] text-slate-700'
                                }`}
                            >
                                {type.label}
                            </Link>
                        ))}
                    </div>

                    {flash?.status ? (
                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.status}
                        </div>
                    ) : null}

                    <section className="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                        <div className="space-y-5 rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                            <input type="hidden" value={form.data.type} />

                            <div className="space-y-4">
                                <label className="block">
                                    <span className="mb-2 block text-sm font-medium">
                                        Gambar template
                                    </span>
                                    <input
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        onChange={onImageChange}
                                        className="block w-full text-sm"
                                    />
                                </label>

                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium">
                                            Lebar asli
                                        </span>
                                        <input
                                            type="number"
                                            min={1}
                                            value={form.data.canvas_width}
                                            onChange={(event) =>
                                                form.setData(
                                                    'canvas_width',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                    </label>

                                    <label className="block">
                                        <span className="mb-2 block text-sm font-medium">
                                            Tinggi asli
                                        </span>
                                        <input
                                            type="number"
                                            min={1}
                                            value={form.data.canvas_height}
                                            onChange={(event) =>
                                                form.setData(
                                                    'canvas_height',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base"
                                        />
                                    </label>
                                </div>
                            </div>

                            <div className="space-y-3">
                                {visibleMarkerKeys.map((key) => (
                                    <div
                                        key={key}
                                        className={`rounded-2xl border p-4 ${
                                            activeMarker === key
                                                ? 'border-[var(--color-brand)] bg-[var(--color-panel)]'
                                                : 'border-[var(--color-border)]'
                                        }`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => setActiveMarker(key)}
                                            className="w-full text-left"
                                        >
                                            <p
                                                className="text-sm font-semibold"
                                                style={{
                                                    color: markerColors[key],
                                                }}
                                            >
                                                {markerLabels[key]}
                                            </p>
                                            <p className="mt-2 text-xs leading-5 text-slate-600">
                                                X{' '}
                                                {Math.round(
                                                    form.data.markers[key].x,
                                                )}{' '}
                                                | Y{' '}
                                                {Math.round(
                                                    form.data.markers[key].y,
                                                )}
                                            </p>
                                            <p className="text-xs leading-5 text-slate-600">
                                                Lebar{' '}
                                                {Math.round(
                                                    form.data.markers[key]
                                                        .width,
                                                )}{' '}
                                                | Tinggi{' '}
                                                {Math.round(
                                                    form.data.markers[key]
                                                        .height,
                                                )}
                                            </p>
                                        </button>

                                        <div className="mt-3 grid grid-cols-2 gap-3">
                                            <label className="block">
                                                <span className="mb-1 block text-xs text-slate-600">
                                                    X
                                                </span>
                                                <input
                                                    type="number"
                                                    min={0}
                                                    value={Math.round(
                                                        form.data.markers[key]
                                                            .x,
                                                    )}
                                                    onChange={(event) =>
                                                        updateMarkerField(
                                                            key,
                                                            'x',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-3 py-2 text-sm"
                                                />
                                            </label>
                                            <label className="block">
                                                <span className="mb-1 block text-xs text-slate-600">
                                                    Y
                                                </span>
                                                <input
                                                    type="number"
                                                    min={0}
                                                    value={Math.round(
                                                        form.data.markers[key]
                                                            .y,
                                                    )}
                                                    onChange={(event) =>
                                                        updateMarkerField(
                                                            key,
                                                            'y',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-3 py-2 text-sm"
                                                />
                                            </label>
                                            <label className="block">
                                                <span className="mb-1 block text-xs text-slate-600">
                                                    Lebar
                                                </span>
                                                <input
                                                    type="number"
                                                    min={1}
                                                    value={Math.round(
                                                        form.data.markers[key]
                                                            .width,
                                                    )}
                                                    onChange={(event) =>
                                                        updateMarkerField(
                                                            key,
                                                            'width',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-3 py-2 text-sm"
                                                />
                                            </label>
                                            <label className="block">
                                                <span className="mb-1 block text-xs text-slate-600">
                                                    Tinggi
                                                </span>
                                                <input
                                                    type="number"
                                                    min={1}
                                                    value={Math.round(
                                                        form.data.markers[key]
                                                            .height,
                                                    )}
                                                    onChange={(event) =>
                                                        updateMarkerField(
                                                            key,
                                                            'height',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="w-full rounded-2xl border border-[var(--color-border)] bg-white px-3 py-2 text-sm"
                                                />
                                            </label>
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() => clearMarker(key)}
                                            className="mt-3 text-sm font-semibold text-[var(--color-brand)]"
                                        >
                                            Reset area ini
                                        </button>
                                    </div>
                                ))}
                            </div>

                            {Object.values(form.errors).length > 0 ? (
                                <div className="space-y-1 text-sm text-red-700">
                                    {Object.values(form.errors).map((error) => (
                                        <p key={error}>{error}</p>
                                    ))}
                                </div>
                            ) : null}

                            <button
                                type="button"
                                onClick={submit}
                                disabled={form.processing}
                                className="rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white disabled:opacity-60"
                            >
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan tanda posisi'}
                            </button>
                        </div>

                        <div className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-6 shadow-sm">
                            {marking.image_url || form.data.template_image ? (
                                <div className="text-center">
                                    <div
                                        onMouseDown={onMouseDown}
                                        onMouseMove={onMouseMove}
                                        onMouseUp={onMouseUp}
                                        onMouseLeave={onMouseUp}
                                        className="relative inline-block max-w-full cursor-crosshair select-none"
                                    >
                                        <img
                                            ref={imageRef}
                                            src={
                                                form.data.template_image
                                                    ? URL.createObjectURL(
                                                          form.data
                                                              .template_image,
                                                      )
                                                    : (marking.image_url ?? '')
                                            }
                                            alt={`Template ${marking.title.toLowerCase()}`}
                                            onLoad={(event) => {
                                                setImageSize({
                                                    width: event.currentTarget
                                                        .clientWidth,
                                                    height: event.currentTarget
                                                        .clientHeight,
                                                });
                                            }}
                                            className="block max-h-[80vh] w-auto max-w-full"
                                        />

                                        {visibleMarkerKeys.map((key) => {
                                            const marker =
                                                form.data.markers[key];

                                            return (
                                                <div
                                                    key={key}
                                                    onMouseDown={(event) =>
                                                        startMoveMarker(
                                                            event,
                                                            key,
                                                        )
                                                    }
                                                    className="absolute cursor-move border-2"
                                                    style={{
                                                        left:
                                                            marker.x * scale.x,
                                                        top: marker.y * scale.y,
                                                        width:
                                                            marker.width *
                                                            scale.x,
                                                        height:
                                                            marker.height *
                                                            scale.y,
                                                        borderColor:
                                                            markerColors[key],
                                                        backgroundColor: `${markerColors[key]}22`,
                                                    }}
                                                >
                                                    <span
                                                        className="absolute top-1 left-1 rounded-full px-2 py-1 text-xs font-semibold text-white"
                                                        style={{
                                                            backgroundColor:
                                                                markerColors[
                                                                    key
                                                                ],
                                                        }}
                                                    >
                                                        {markerLabels[key]}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ) : (
                                <div className="flex min-h-[420px] items-center justify-center rounded-[20px] border border-dashed border-[var(--color-border)] bg-[var(--color-panel)] px-6 text-center text-sm leading-6 text-slate-700">
                                    Upload gambar template dulu, lalu tarik area
                                    nama langsung di atas gambar.
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
