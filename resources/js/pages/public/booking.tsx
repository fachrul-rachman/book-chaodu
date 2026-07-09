import { Head, Link, usePage } from '@inertiajs/react';
import type { ChangeEvent, FormEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    createIdempotencyKey,
    formatCurrency,
    onlyDigits,
} from '@/lib/booking';

/* ─── Types (unchanged) ───────────────────────────────────────────────────── */

type PackageItem = {
    code: 'PRAYER' | 'INCENSE' | 'COMBO';
    name: string;
    description: string | null;
    price: string;
    meal_quota: number;
    requires_table: boolean;
    requires_incense: boolean;
    available: boolean;
    unavailable_reason: string | null;
    image_url: string | null;
};

type PreviewTemplate = {
    title: string;
    top_label: string;
    bottom_label: string;
};

type PrayerMarker = {
    x: number;
    y: number;
    width: number;
    height: number;
};

type PrayerPreviewTemplate = PreviewTemplate & {
    image_url: string | null;
    canvas_width: number;
    canvas_height: number;
    markers: {
        single: PrayerMarker;
        left: PrayerMarker;
        right: PrayerMarker;
    };
};

type Props = {
    packages: PackageItem[];
    payment: {
        bank_name: string | null;
        bank_account_holder: string | null;
        virtual_account_mode: 'FIXED' | 'POOL';
        accounts_by_package: Partial<Record<PackageItem['code'], string | null>>;
        hold_minutes: number;
    };
    limits: {
        upload_max_mb: number;
        ocr_upload_max_mb: number;
    };
    captcha: {
        enabled: boolean;
        site_key: string | null;
    };
    preview: {
        render_url: string;
        prayer: PrayerPreviewTemplate;
        incense: PrayerPreviewTemplate;
    };
};

type NameEntry = {
    indonesian_name: string;
    mandarin_name: string;
    source_image: File | null;
    source_image_preview: string | null;
    read_status: 'idle' | 'reading' | 'success' | 'failed';
    read_message: string | null;
};

type FormState = {
    idempotency_key: string;
    customer_name: string;
    customer_phone_local: string;
    customer_email: string;
    attendee_count: string;
    package_code: '' | PackageItem['code'];
    deceased_names: NameEntry[];
    incense_name: NameEntry;
    vegetarian_quantity: string;
    non_vegetarian_quantity: string;
    sender_name: string;
    transfer_date: string;
    proof: File | null;
    referral_source:
        '' | 'TEMAN' | 'KELUARGA' | 'MEDIA_SOSIAL' | 'WEBSITE' | 'AGENT';
    agent_name: string;
    confirmation_checked: boolean;
    captcha_token: string;
};

type ErrorBag = Record<string, string>;

type VirtualAccountReservation = {
    package_code: PackageItem['code'];
    account_number: string;
    bank_name: string | null;
    account_holder: string | null;
    expires_at: string | null;
};

type TurnstileApi = {
    render: (
        container: HTMLElement,
        options: {
            sitekey: string;
            callback: (token: string) => void;
            'expired-callback': () => void;
            'error-callback': () => void;
        },
    ) => void;
};

const steps = [
    'Data\nPemesan',
    'Pilih\nPaket',
    'Atur\nMakanan',
    'Isi\nBayaran',
    'Info\nTambahan',
    'Periksa\nUlang',
] as const;

const BOOKING_FLYER_PATH = '/images/booking/flyer chaodu.jpg';
const BOOKING_FLYER_SRC = encodeURI(BOOKING_FLYER_PATH);
const BOOKING_FLYER_DISMISS_KEY = `booking-flyer-dismissed:${BOOKING_FLYER_PATH}`;

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

/* ─── Helpers (unchanged) ─────────────────────────────────────────────────── */

function blankName(): NameEntry {
    return {
        indonesian_name: '',
        mandarin_name: '',
        source_image: null,
        source_image_preview: null,
        read_status: 'idle',
        read_message: null,
    };
}

function isValidEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

function formatRemainingTime(totalSeconds: number): string {
    const safeSeconds = Math.max(0, totalSeconds);
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;

    if (hours > 0) {
        return `${hours}j ${minutes}m`;
    }

    return `${minutes}m ${seconds}d`;
}

function normalizePaymentAmount(value: string | number): string {
    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return '';
    }

    return String(Math.round(numeric));
}

function labelReferralSource(value: FormState['referral_source']): string {
    return {
        TEMAN: 'Teman',
        KELUARGA: 'Keluarga',
        MEDIA_SOSIAL: 'Media sosial',
        WEBSITE: 'Website',
        AGENT: 'Agent',
        '': '-',
    }[value];
}

function pickPrayerName(
    entry: NameEntry,
): { text: string; vertical: boolean } | null {
    const mandarin = normalizeDisplayText(entry.mandarin_name);
    const indonesian = normalizeDisplayText(entry.indonesian_name);
    const text = mandarin || indonesian.toUpperCase();

    if (!text) {
        return null;
    }

    return { text, vertical: Boolean(mandarin) };
}

function normalizeDisplayText(value: string): string {
    return value
        .replace(/\r\n/g, '\n')
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .join('\n');
}

function buildPreviewImageUrl(
    baseUrl: string,
    type: 'A' | 'B',
    index: number,
    name: { text: string; vertical: boolean } | null,
    rawNames?: {
        indonesian?: string;
        mandarin?: string;
    },
): string | null {
    if (!name) {
        return null;
    }

    const params = new URLSearchParams({
        type,
        index: String(index),
    });

    if (type === 'A') {
        params.set('name_1_indonesian', rawNames?.indonesian ?? '');
        params.set('name_1_mandarin', rawNames?.mandarin ?? '');
    } else {
        params.set('incense_indonesian', rawNames?.indonesian ?? '');
        params.set('incense_mandarin', rawNames?.mandarin ?? '');
    }

    return `${baseUrl}?${params.toString()}`;
}

function getStepFromErrors(errorBag: ErrorBag): number {
    const keys = Object.keys(errorBag);

    if (
        keys.some((key) =>
            [
                'customer_name',
                'customer_phone_local',
                'customer_email',
                'attendee_count',
            ].includes(key),
        )
    ) {
        return 1;
    }

    if (
        keys.some(
            (key) =>
                key === 'package_code' ||
                key.startsWith('deceased_names') ||
                key.startsWith('incense_name'),
        )
    ) {
        return 2;
    }

    if (
        keys.some((key) =>
            ['vegetarian_quantity', 'non_vegetarian_quantity'].includes(key),
        )
    ) {
        return 3;
    }

    if (
        keys.some((key) =>
            [
                'sender_name',
                'transfer_date',
                'proof',
            ].includes(key),
        )
    ) {
        return 4;
    }

    if (keys.some((key) => ['referral_source', 'agent_name'].includes(key))) {
        return 5;
    }

    return 6;
}

/* ─── Sub-components ──────────────────────────────────────────────────────── */

function PackageCard({
    item,
    selected,
    onSelect,
    disabled = false,
}: {
    item: PackageItem;
    selected: boolean;
    onSelect: () => void;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            disabled={!item.available || disabled}
            className={[
                'w-full overflow-hidden rounded-2xl border-2 text-left transition',
                selected
                    ? 'border-[#8B1A1A] bg-[#FDF8F0] ring-2 ring-[#8B1A1A]/20'
                    : 'border-[#E8D5C0] bg-white hover:border-[#C84040]',
                !item.available || disabled
                    ? 'cursor-not-allowed opacity-55'
                    : '',
            ].join(' ')}
        >
            {/* Image */}
            <div className="aspect-[4/3] bg-[#FDF8F0]">
                {item.image_url ? (
                    <img
                        src={item.image_url}
                        alt={item.name}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <div className="flex h-full items-center justify-center px-4 text-center text-sm text-[#5C3D2E]/60">
                        Foto belum tersedia
                    </div>
                )}
            </div>

            {/* Content */}
            <div className="space-y-3 p-5">
                {/* Radio indicator */}
                <div className="flex items-center gap-3">
                    <span
                        className={[
                            'flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border-2 transition',
                            selected
                                ? 'border-[#8B1A1A] bg-[#8B1A1A]'
                                : 'border-[#E8D5C0]',
                        ].join(' ')}
                    >
                        {selected && (
                            <span className="h-2 w-2 rounded-full bg-white" />
                        )}
                    </span>
                    <p className="text-xl font-semibold text-[#2C1810]">
                        {item.name}
                    </p>
                </div>

                <p className="text-sm leading-6 text-[#5C3D2E]">
                    {item.description}
                </p>
                <p className="text-lg font-semibold text-[#8B1A1A]">
                    {formatCurrency(item.price)}
                </p>
                <p className="text-sm text-[#5C3D2E]">
                    free {item.meal_quota} porsi konsumsi
                </p>

                {item.available ? (
                    <span className="inline-block rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                        Masih tersedia
                    </span>
                ) : (
                    <span className="inline-block rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                        {item.unavailable_reason ?? 'Sedang tidak tersedia'}
                    </span>
                )}
            </div>
        </button>
    );
}

function ErrorText({ value }: { value?: string }) {
    if (!value) {
        return null;
    }

    return <p className="mt-2 text-sm font-medium text-red-700">{value}</p>;
}

function PhotoReadStatus({ entry }: { entry: NameEntry }) {
    if (entry.read_status === 'idle' || !entry.read_message) {
        return null;
    }

    const tone =
        entry.read_status === 'success'
            ? 'text-emerald-700'
            : entry.read_status === 'reading'
              ? 'text-[#5C3D2E]'
              : 'text-amber-700';

    return <p className={`mt-2 text-sm ${tone}`}>{entry.read_message}</p>;
}

function NameCard({
    title,
    indonesianLabel,
    mandarinLabel,
    photoLabel,
    readButtonLabel,
    entry,
    mandarinError,
    photoError,
    maxUploadMb,
    indonesianLocked,
    mandarinLocked,
    onChangeName,
    onChangePhoto,
    onReadPhoto,
    multiline = false,
}: {
    title: string;
    indonesianLabel: string;
    mandarinLabel: string;
    photoLabel: string;
    readButtonLabel: string;
    entry: NameEntry;
    mandarinError?: string;
    photoError?: string;
    maxUploadMb: number;
    indonesianLocked: boolean;
    mandarinLocked: boolean;
    onChangeName: (
        key: 'indonesian_name' | 'mandarin_name',
        value: string,
    ) => void;
    onChangePhoto: (file: File | null) => void;
    onReadPhoto: () => void;
    multiline?: boolean;
}) {
    return (
        <div className="rounded-xl border border-[#E8D084] bg-[#FDF6DC] p-4">
            <p className="mb-3 text-sm font-semibold text-[#B8860B]">{title}</p>

            <div className="grid gap-4 lg:grid-cols-[1fr_1fr_0.95fr]">
                <label className="block">
                    <span className="mb-2 block text-sm font-medium text-[#2C1810]">
                        Nama Indonesia
                    </span>
                    {multiline ? (
                        <textarea
                            aria-label={indonesianLabel}
                            rows={3}
                            value={entry.indonesian_name}
                            disabled={indonesianLocked}
                            onChange={(event) =>
                                onChangeName('indonesian_name', event.target.value)
                            }
                            className={`w-full rounded-xl border-2 px-4 py-3 text-base text-[#2C1810] outline-none ${
                                indonesianLocked
                                    ? 'cursor-not-allowed border-[#E8D5C0] bg-slate-100 text-slate-500'
                                    : 'border-[#E8D5C0] bg-white focus:border-[#8B1A1A]'
                            }`}
                        />
                    ) : (
                        <input
                            aria-label={indonesianLabel}
                            type="text"
                            value={entry.indonesian_name}
                            disabled={indonesianLocked}
                            onChange={(event) =>
                                onChangeName('indonesian_name', event.target.value)
                            }
                            className={`w-full rounded-xl border-2 px-4 py-3 text-base text-[#2C1810] outline-none ${
                                indonesianLocked
                                    ? 'cursor-not-allowed border-[#E8D5C0] bg-slate-100 text-slate-500'
                                    : 'border-[#E8D5C0] bg-white focus:border-[#8B1A1A]'
                            }`}
                        />
                    )}
                </label>

                <label className="block">
                    <span className="mb-2 block text-sm font-medium text-[#2C1810]">
                        Nama Mandarin
                    </span>
                    {multiline ? (
                        <textarea
                            aria-label={mandarinLabel}
                            rows={3}
                            value={entry.mandarin_name}
                            disabled={mandarinLocked}
                            onChange={(event) =>
                                onChangeName('mandarin_name', event.target.value)
                            }
                            className={`w-full rounded-xl border-2 px-4 py-3 text-base text-[#2C1810] outline-none ${
                                mandarinLocked
                                    ? 'cursor-not-allowed border-[#E8D5C0] bg-slate-100 text-slate-500'
                                    : 'border-[#E8D5C0] bg-white focus:border-[#8B1A1A]'
                            }`}
                        />
                    ) : (
                        <input
                            aria-label={mandarinLabel}
                            type="text"
                            value={entry.mandarin_name}
                            disabled={mandarinLocked}
                            onChange={(event) =>
                                onChangeName('mandarin_name', event.target.value)
                            }
                            className={`w-full rounded-xl border-2 px-4 py-3 text-base text-[#2C1810] outline-none ${
                                mandarinLocked
                                    ? 'cursor-not-allowed border-[#E8D5C0] bg-slate-100 text-slate-500'
                                    : 'border-[#E8D5C0] bg-white focus:border-[#8B1A1A]'
                            }`}
                        />
                    )}
                    <p className="mt-2 text-sm text-[#5C3D2E]">
                        {mandarinLocked
                            ? 'Kolom ini dikunci karena Anda sedang memakai nama Indonesia.'
                            : indonesianLocked
                              ? 'Kolom ini aktif karena Anda sedang memakai nama Mandarin atau foto.'
                              : 'Pilih salah satu: nama Indonesia atau nama Mandarin/foto.'}
                    </p>
                    <ErrorText value={mandarinError} />
                </label>

                <div>
                    <label className="block">
                        <span className="mb-2 block text-sm font-medium text-[#2C1810]">
                            {photoLabel}
                        </span>
                        <input
                            aria-label={photoLabel}
                            type="file"
                            accept=".jpg,.jpeg,.png"
                            disabled={mandarinLocked}
                            onChange={(event: ChangeEvent<HTMLInputElement>) =>
                                onChangePhoto(event.target.files?.[0] ?? null)
                            }
                            className={`block w-full text-sm ${
                                mandarinLocked
                                    ? 'cursor-not-allowed text-slate-400'
                                    : 'text-[#5C3D2E]'
                            }`}
                        />
                    </label>
                    <p className="mt-2 text-sm text-[#5C3D2E]">
                        {mandarinLocked
                            ? 'Foto dikunci karena Anda sedang memakai nama Indonesia.'
                            : `Format JPG atau PNG. Maksimal ${maxUploadMb} MB.`}
                    </p>
                    {entry.source_image_preview && (
                        <div className="mt-3 overflow-hidden rounded-xl border border-[#E8D084]">
                            <img
                                src={entry.source_image_preview}
                                alt={photoLabel}
                                className="h-36 w-full object-cover"
                            />
                        </div>
                    )}
                    {entry.source_image && (
                        <p className="mt-2 text-sm text-[#5C3D2E]">
                            {entry.source_image.name}
                        </p>
                    )}
                    <button
                        type="button"
                        onClick={onReadPhoto}
                        disabled={entry.read_status === 'reading' || mandarinLocked}
                        className="mt-3 rounded-full border-2 border-[#8B1A1A] px-4 py-2 text-sm font-semibold text-[#8B1A1A] transition hover:bg-[#FDF8F0] disabled:opacity-50"
                    >
                        {mandarinLocked
                            ? 'Pakai nama Indonesia'
                            : entry.read_status === 'reading'
                            ? 'Sedang membaca foto...'
                            : readButtonLabel}
                    </button>
                    <PhotoReadStatus entry={entry} />
                    <ErrorText value={photoError} />
                </div>
            </div>
        </div>
    );
}

function PrayerPaperPreview({
    template,
    name,
    imageUrl,
    kind,
}: {
    template: PrayerPreviewTemplate;
    name: { text: string; vertical: boolean } | null;
    imageUrl: string | null;
    kind: 'prayer' | 'incense';
}) {
    const textColor = kind === 'prayer' ? '#000000' : '#E82C2A';

    if (!template.image_url) {
        return (
            <section className="rounded-2xl border border-[#E8D5C0] bg-white p-5 shadow-sm sm:p-6">
                <p className="text-sm font-semibold text-[#B8860B]">
                    {template.title}
                </p>
                <div className="mt-4 aspect-[3/4] rounded-xl border border-[#E8D5C0] bg-[#FDF8F0] p-6">
                    <div className="flex h-full flex-col justify-between rounded-xl border border-dashed border-[#E8D084] px-5 py-6 text-center">
                        <div>
                            <p className="text-sm font-medium tracking-[0.16em] text-[#5C3D2E]/60 uppercase">
                                {template.top_label}
                            </p>
                            <p className="mt-4 text-lg font-semibold text-[#2C1810]">
                                Contoh susunan tulisan
                            </p>
                        </div>
                        <div className="space-y-3">
                            <p
                                className="text-2xl font-semibold"
                                style={{ color: textColor }}
                            >
                                {name?.text ?? 'Nama akan tampil di sini'}
                            </p>
                        </div>
                        <p className="text-sm text-[#5C3D2E]/60">
                            {template.bottom_label}
                        </p>
                    </div>
                </div>
            </section>
        );
    }

    return (
        <section className="rounded-2xl border border-[#E8D5C0] bg-white p-5 shadow-sm sm:p-6">
            <p className="text-sm font-semibold text-[#B8860B]">
                {template.title}
            </p>
            <div className="mt-4 rounded-xl border border-[#E8D5C0] bg-[#FDF8F0] p-4">
                <div className="mb-4 flex items-start justify-between gap-3 text-sm text-[#5C3D2E]">
                    <p>{template.top_label}</p>
                    <p className="text-right">{template.bottom_label}</p>
                </div>
                <div className="mx-auto w-full max-w-[220px]">
                    <div className="overflow-hidden rounded-xl">
                        <img
                            src={imageUrl ?? template.image_url}
                            alt={template.title}
                            className="block h-auto w-full"
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}

/* ─── Shared input class ──────────────────────────────────────────────────── */

const inputCls =
    'w-full rounded-xl border-2 border-[#E8D5C0] bg-white px-4 py-3 text-base text-[#2C1810] outline-none transition focus:border-[#8B1A1A]';

/* ─── Main page ───────────────────────────────────────────────────────────── */

export default function PublicBookingPage() {
    const { packages, payment, limits, captcha, preview } =
        usePage<Props>().props;
    const [step, setStep] = useState(1);
    const [processing, setProcessing] = useState(false);
    const [reservingVirtualAccount, setReservingVirtualAccount] =
        useState(false);
    const [showFlyerModal, setShowFlyerModal] = useState(false);
    const [generalError, setGeneralError] = useState<string | null>(null);
    const [errors, setErrors] = useState<ErrorBag>({});
    const [copiedAccountNumber, setCopiedAccountNumber] = useState(false);
    const [copiedAmount, setCopiedAmount] = useState(false);
    const [virtualAccount, setVirtualAccount] =
        useState<VirtualAccountReservation | null>(null);
    const [reservationRemainingSeconds, setReservationRemainingSeconds] =
        useState<number | null>(null);
    const captchaContainerRef = useRef<HTMLDivElement | null>(null);

    const [form, setForm] = useState<FormState>({
        idempotency_key: createIdempotencyKey(),
        customer_name: '',
        customer_phone_local: '',
        customer_email: '',
        attendee_count: '',
        package_code: '',
        deceased_names: [blankName(), blankName()],
        incense_name: blankName(),
        vegetarian_quantity: '0',
        non_vegetarian_quantity: '0',
        sender_name: '',
        transfer_date: '',
        proof: null,
        referral_source: '',
        agent_name: '',
        confirmation_checked: false,
        captcha_token: '',
    });

    const selectedPackage = useMemo(
        () =>
            packages.find(
                (item: PackageItem) => item.code === form.package_code,
            ) ?? null,
        [packages, form.package_code],
    );
    const headerBannerUrl = '/images/booking/header.jpg';
    const [headerBannerMissing, setHeaderBannerMissing] = useState(false);
    const isPoolVirtualAccount = payment.virtual_account_mode === 'POOL';
    const packageAccountNumber = selectedPackage
        ? (isPoolVirtualAccount
              ? virtualAccount?.account_number ?? null
              : payment.accounts_by_package[selectedPackage.code] ?? null)
        : null;
    const reservationExpired =
        isPoolVirtualAccount &&
        reservationRemainingSeconds !== null &&
        reservationRemainingSeconds <= 0;

    /* ── Captcha (unchanged) ── */
    useEffect(() => {
        if (
            !captcha.enabled ||
            !captcha.site_key ||
            !captchaContainerRef.current
        ) {
            return;
        }

        const siteKey = captcha.site_key;
        const initialize = () => {
            if (!window.turnstile || !captchaContainerRef.current) {
                return;
            }

            captchaContainerRef.current.innerHTML = '';
            window.turnstile.render(captchaContainerRef.current, {
                sitekey: siteKey,
                callback: (token: string) =>
                    setForm((c) => ({ ...c, captcha_token: token })),
                'expired-callback': () =>
                    setForm((c) => ({ ...c, captcha_token: '' })),
                'error-callback': () =>
                    setForm((c) => ({ ...c, captcha_token: '' })),
            });
        };

        if (window.turnstile) {
            initialize();

            return;
        }

        const script = document.createElement('script');
        script.src =
            'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        script.onload = initialize;
        document.head.appendChild(script);

        return () => {
            script.remove();
        };
    }, [captcha.enabled, captcha.site_key]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        if (!window.localStorage.getItem(BOOKING_FLYER_DISMISS_KEY)) {
            setShowFlyerModal(true);
        }
    }, []);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        const previousOverflow = document.body.style.overflow;

        if (showFlyerModal) {
            document.body.style.overflow = 'hidden';
        }

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [showFlyerModal]);

    useEffect(() => {
        if (!isPoolVirtualAccount || !virtualAccount?.expires_at) {
            return;
        }

        const updateRemaining = () => {
            const expiresAt = new Date(virtualAccount.expires_at as string);
            const nextValue = Math.max(
                0,
                Math.floor((expiresAt.getTime() - Date.now()) / 1000),
            );

            setReservationRemainingSeconds(nextValue);
        };

        updateRemaining();
        const timer = window.setInterval(updateRemaining, 1000);

        return () => {
            window.clearInterval(timer);
        };
    }, [isPoolVirtualAccount, virtualAccount]);

    /* ── Form helpers (unchanged logic) ── */
    const clearErrors = (prefixes: string[]) => {
        setErrors((current) =>
            Object.fromEntries(
                Object.entries(current).filter(
                    ([key]) =>
                        !prefixes.some(
                            (prefix) =>
                                key === prefix || key.startsWith(`${prefix}.`),
                        ),
                ),
            ),
        );
    };

    const setField = <K extends keyof FormState>(
        key: K,
        value: FormState[K],
    ) => {
        setForm((current) => ({ ...current, [key]: value }));
        clearErrors([String(key)]);
        setGeneralError(null);
    };

    const setDeceasedName = (
        index: number,
        key: 'indonesian_name' | 'mandarin_name',
        value: string,
    ) => {
        setForm((current) => ({
            ...current,
            deceased_names: current.deceased_names.map((item, itemIndex) => {
                if (itemIndex !== index) {
                    return item;
                }

                if (key === 'indonesian_name') {
                    if (item.source_image_preview) {
                        URL.revokeObjectURL(item.source_image_preview);
                    }

                    return {
                        ...item,
                        indonesian_name: value,
                        mandarin_name: value.trim() !== '' ? '' : item.mandarin_name,
                        source_image: value.trim() !== '' ? null : item.source_image,
                        source_image_preview:
                            value.trim() !== '' ? null : item.source_image_preview,
                        read_status: value.trim() !== '' ? 'idle' : item.read_status,
                        read_message: value.trim() !== '' ? null : item.read_message,
                    };
                }

                return {
                    ...item,
                    mandarin_name: value,
                    indonesian_name: value.trim() !== '' ? '' : item.indonesian_name,
                };
            }),
        }));
        clearErrors(['deceased_names']);
        setGeneralError(null);
    };

    const setIncenseName = (
        key: 'indonesian_name' | 'mandarin_name',
        value: string,
    ) => {
        setForm((current) => {
            if (key === 'indonesian_name') {
                if (value.trim() !== '' && current.incense_name.source_image_preview) {
                    URL.revokeObjectURL(current.incense_name.source_image_preview);
                }

                return {
                    ...current,
                    incense_name: {
                        ...current.incense_name,
                        indonesian_name: value,
                        mandarin_name:
                            value.trim() !== '' ? '' : current.incense_name.mandarin_name,
                        source_image:
                            value.trim() !== '' ? null : current.incense_name.source_image,
                        source_image_preview:
                            value.trim() !== ''
                                ? null
                                : current.incense_name.source_image_preview,
                        read_status:
                            value.trim() !== '' ? 'idle' : current.incense_name.read_status,
                        read_message:
                            value.trim() !== '' ? null : current.incense_name.read_message,
                    },
                };
            }

            return {
                ...current,
                incense_name: {
                    ...current.incense_name,
                    mandarin_name: value,
                    indonesian_name:
                        value.trim() !== ''
                            ? ''
                            : current.incense_name.indonesian_name,
                },
            };
        });
        clearErrors(['incense_name']);
        setGeneralError(null);
    };

    const setDeceasedSourceImage = (index: number, file: File | null) => {
        const previewUrl = file ? URL.createObjectURL(file) : null;
        setForm((current) => ({
            ...current,
            deceased_names: current.deceased_names.map((item, itemIndex) => {
                if (itemIndex !== index) {
                    return item;
                }

                if (item.source_image_preview) {
                    URL.revokeObjectURL(item.source_image_preview);
                }

                return {
                    ...item,
                    source_image: file,
                    source_image_preview: previewUrl,
                    indonesian_name: file ? '' : item.indonesian_name,
                    read_status: 'idle',
                    read_message: null,
                };
            }),
        }));
        clearErrors(['deceased_names']);
        setGeneralError(null);
    };

    const setIncenseSourceImage = (file: File | null) => {
        const previewUrl = file ? URL.createObjectURL(file) : null;
        setForm((current) => {
            if (current.incense_name.source_image_preview) {
                URL.revokeObjectURL(current.incense_name.source_image_preview);
            }

            return {
                ...current,
                incense_name: {
                    ...current.incense_name,
                    source_image: file,
                    source_image_preview: previewUrl,
                    indonesian_name: file ? '' : current.incense_name.indonesian_name,
                    read_status: 'idle',
                    read_message: null,
                },
            };
        });
        clearErrors(['incense_name']);
        setGeneralError(null);
    };

    const choosePackage = (item: PackageItem) => {
        if (reservingVirtualAccount) {
            return;
        }

        if (form.package_code && form.package_code !== item.code) {
            setCopiedAccountNumber(false);
            setCopiedAmount(false);
            setVirtualAccount(null);
            setReservationRemainingSeconds(null);
        }

        setField('package_code', item.code);
        setField('vegetarian_quantity', '0');
        setField('non_vegetarian_quantity', '0');
    };

    const reserveVirtualAccount = async () => {
        if (reservingVirtualAccount) {
            return false;
        }

        if (!selectedPackage) {
            setErrors({
                package_code: 'Silakan pilih paket terlebih dahulu.',
            });
            setGeneralError('Silakan pilih paket terlebih dahulu.');

            return false;
        }

        if (!isPoolVirtualAccount) {
            return true;
        }

        if (
            virtualAccount?.package_code === selectedPackage.code &&
            virtualAccount.account_number &&
            !reservationExpired
        ) {
            return true;
        }

        setReservingVirtualAccount(true);
        setGeneralError(null);

        try {
            const response = await fetch('/api/public/virtual-accounts/reserve', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    idempotency_key: form.idempotency_key,
                    package_code: selectedPackage.code,
                }),
            });
            const data = (await response.json().catch(() => ({}))) as
                | VirtualAccountReservation
                | { message?: string; errors?: Record<string, string[]> };

            if (
                response.ok &&
                'account_number' in data &&
                typeof data.account_number === 'string'
            ) {
                setVirtualAccount(data);
                clearErrors(['package_code']);

                return true;
            }

            const packageError =
                'errors' in data && data.errors?.package_code?.[0]
                    ? data.errors.package_code[0]
                    : ('message' in data && data.message) ||
                      'Nomor pembayaran untuk paket ini belum tersedia. Silakan coba lagi nanti.';

            setErrors({
                package_code: packageError,
            });
            setGeneralError(packageError);

            return false;
        } catch {
            const fallbackMessage =
                'Nomor pembayaran untuk paket ini belum tersedia. Silakan coba lagi nanti.';

            setErrors({
                package_code: fallbackMessage,
            });
            setGeneralError(fallbackMessage);

            return false;
        } finally {
            setReservingVirtualAccount(false);
        }
    };

    const ensurePackageAccountExists = () => {
        if (step < 3) {
            return true;
        }

        if (reservationExpired) {
            setErrors({
                package_code:
                    'Nomor pembayaran sudah lewat waktu. Silakan pilih paket lagi.',
            });
            setGeneralError(
                'Nomor pembayaran sudah lewat waktu. Silakan pilih paket lagi.',
            );
            setStep(2);

            return false;
        }

        if (!selectedPackage || !packageAccountNumber) {
            setErrors({
                package_code:
                    'Nomor pembayaran untuk paket ini belum tersedia. Silakan coba lagi nanti.',
            });
            setGeneralError(
                'Nomor pembayaran untuk paket ini belum tersedia. Silakan coba lagi nanti.',
            );
            setStep(2);

            return false;
        }

        return true;
    };

    const applyDeceasedReadState = (
        index: number,
        readStatus: NameEntry['read_status'],
        readMessage: string | null,
        mandarinName?: string,
    ) => {
        setForm((current) => ({
            ...current,
            deceased_names: current.deceased_names.map((item, itemIndex) =>
                itemIndex === index
                    ? {
                          ...item,
                          mandarin_name: mandarinName ?? item.mandarin_name,
                          indonesian_name:
                              mandarinName !== undefined
                                  ? ''
                                  : item.indonesian_name,
                          read_status: readStatus,
                          read_message: readMessage,
                      }
                    : item,
            ),
        }));
        clearErrors(['deceased_names']);
    };

    const applyIncenseReadState = (
        readStatus: NameEntry['read_status'],
        readMessage: string | null,
        mandarinName?: string,
    ) => {
        setForm((current) => ({
            ...current,
            incense_name: {
                ...current.incense_name,
                mandarin_name:
                    mandarinName ?? current.incense_name.mandarin_name,
                indonesian_name:
                    mandarinName !== undefined
                        ? ''
                        : current.incense_name.indonesian_name,
                read_status: readStatus,
                read_message: readMessage,
            },
        }));
        clearErrors(['incense_name']);
    };

    const readPhoto = async (target: 'deceased' | 'incense', index = 0) => {
        const entry =
            target === 'deceased'
                ? form.deceased_names[index]
                : form.incense_name;

        if (!entry?.source_image) {
            if (target === 'deceased') {
                applyDeceasedReadState(
                    index,
                    'failed',
                    'Pilih foto terlebih dahulu.',
                );
            } else {
                applyIncenseReadState('failed', 'Pilih foto terlebih dahulu.');
            }

            return;
        }

        if (target === 'deceased') {
            applyDeceasedReadState(index, 'reading', 'Sedang membaca foto...');
        } else {
            applyIncenseReadState('reading', 'Sedang membaca foto...');
        }

        const payload = new FormData();
        payload.append('source_image', entry.source_image);

        try {
            const response = await fetch('/api/public/ocr', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
            });
            const data = (await response.json().catch(() => ({}))) as {
                text?: string;
                message?: string;
            };

            if (response.ok && data.text?.trim()) {
                const message =
                    'Tulisan dari foto sudah masuk. Anda masih bisa ubah bila perlu.';

                if (target === 'deceased') {
                    applyDeceasedReadState(
                        index,
                        'success',
                        message,
                        data.text.trim(),
                    );
                } else {
                    applyIncenseReadState('success', message, data.text.trim());
                }

                return;
            }

            const fallbackMessage =
                response.status === 429
                    ? 'Terlalu banyak percobaan membaca foto. Silakan tunggu sebentar lalu coba lagi.'
                    : (data.message ??
                      'Tulisan pada foto belum bisa dibaca. Anda tetap bisa isi manual.');

            if (target === 'deceased') {
                applyDeceasedReadState(index, 'failed', fallbackMessage);
            } else {
                applyIncenseReadState('failed', fallbackMessage);
            }
        } catch {
            const fallbackMessage =
                'Tulisan pada foto belum bisa dibaca. Anda tetap bisa isi manual.';

            if (target === 'deceased') {
                applyDeceasedReadState(index, 'failed', fallbackMessage);
            } else {
                applyIncenseReadState('failed', fallbackMessage);
            }
        }
    };

    /* ── Validation (unchanged) ── */
    const validateStep = (currentStep: number): boolean => {
        const nextErrors: ErrorBag = {};

        if (currentStep === 1) {
            if (!form.customer_name.trim()) {
                nextErrors.customer_name = 'Nama pemesan wajib diisi.';
            }

            if (!/^[1-9][0-9]{7,14}$/.test(form.customer_phone_local)) {
                nextErrors.customer_phone_local =
                    'Nomor telepon setelah +62 harus benar.';
            }

            if (!form.customer_email.trim()) {
                nextErrors.customer_email = 'Email wajib diisi.';
            } else if (!isValidEmail(form.customer_email)) {
                nextErrors.customer_email = 'Format email belum benar.';
            }

            if (!form.attendee_count || Number(form.attendee_count) < 1) {
                nextErrors.attendee_count = 'Jumlah yang hadir minimal 1.';
            }
        }

        if (currentStep === 2) {
            if (!selectedPackage) {
                nextErrors.package_code =
                    'Silakan pilih paket terlebih dahulu.';
            } else {
                if (
                    (selectedPackage.code === 'PRAYER' ||
                        selectedPackage.code === 'COMBO') &&
                    form.deceased_names.filter(
                        (item) =>
                            item.indonesian_name.trim() ||
                            item.mandarin_name.trim(),
                    ).length < 1
                ) {
                    nextErrors.deceased_names = 'Isi minimal 1 nama.';
                }

                if (
                    (selectedPackage.code === 'INCENSE' ||
                        selectedPackage.code === 'COMBO') &&
                    !form.incense_name.indonesian_name.trim() &&
                    !form.incense_name.mandarin_name.trim()
                ) {
                    nextErrors.incense_name =
                        'Isi nama orang atau keluarga yang ingin didoakan.';
                }
            }
        }

        if (currentStep === 4) {
            if (!packageAccountNumber) {
                nextErrors.package_code =
                    'Nomor pembayaran untuk paket ini belum tersedia. Silakan coba lagi nanti.';
            }

            if (!form.sender_name.trim()) {
                nextErrors.sender_name = 'Nama pengirim wajib diisi.';
            }

            if (!form.transfer_date) {
                nextErrors.transfer_date = 'Tanggal transfer wajib diisi.';
            }

            if (!form.proof) {
                nextErrors.proof = 'Bukti transfer wajib diunggah.';
            }
        }

        if (currentStep === 3 && selectedPackage) {
            const mealTotal =
                Number(form.vegetarian_quantity || 0) +
                Number(form.non_vegetarian_quantity || 0);

            if (mealTotal > selectedPackage.meal_quota) {
                nextErrors.vegetarian_quantity =
                    `Total makanan maksimal ${selectedPackage.meal_quota} porsi.`;
                nextErrors.non_vegetarian_quantity =
                    `Total makanan maksimal ${selectedPackage.meal_quota} porsi.`;
            }
        }

        if (currentStep === 5) {
            if (!form.referral_source) {
                nextErrors.referral_source = 'Silakan pilih sumber informasi.';
            }

            if (form.referral_source === 'AGENT' && !form.agent_name.trim()) {
                nextErrors.agent_name = 'Nama agent wajib diisi.';
            }
        }

        if (currentStep === 6) {
            if (!form.confirmation_checked) {
                nextErrors.confirmation_checked = 'Silakan centang konfirmasi.';
            }

            if (captcha.enabled && !form.captcha_token) {
                nextErrors.captcha_token =
                    'Pemeriksaan keamanan belum selesai.';
            }
        }

        setErrors(nextErrors);

        return Object.keys(nextErrors).length === 0;
    };

    const nextStep = async () => {
        if (!validateStep(step)) {
            return;
        }

        if (step >= 3 && !ensurePackageAccountExists()) {
            return;
        }

        if (step === 2 && isPoolVirtualAccount) {
            const reserved = await reserveVirtualAccount();

            if (!reserved) {
                return;
            }
        }

        setStep((current) => Math.min(current + 1, steps.length));
    };

    const previousStep = () => {
        setStep((current) => Math.max(current - 1, 1));
    };

    const copyAccountNumber = async () => {
        if (!packageAccountNumber) {
            return;
        }

        await navigator.clipboard.writeText(packageAccountNumber);
        setCopiedAccountNumber(true);
        window.setTimeout(() => setCopiedAccountNumber(false), 2000);
    };

    const copyPaymentAmount = async () => {
        if (!selectedPackage) {
            return;
        }

        await navigator.clipboard.writeText(
            normalizePaymentAmount(selectedPackage.price),
        );
        setCopiedAmount(true);
        window.setTimeout(() => setCopiedAmount(false), 2000);
    };

    /* ── Submit (unchanged) ── */
    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!ensurePackageAccountExists() || !validateStep(6)) {
            return;
        }

        setProcessing(true);
        setGeneralError(null);

        const payload = new FormData();
        payload.append('idempotency_key', form.idempotency_key);
        payload.append('customer_name', form.customer_name);
        payload.append('customer_phone_local', form.customer_phone_local);
        payload.append('customer_email', form.customer_email);
        payload.append('attendee_count', form.attendee_count);
        payload.append('package_code', form.package_code);
        payload.append('vegetarian_quantity', form.vegetarian_quantity);
        payload.append('non_vegetarian_quantity', form.non_vegetarian_quantity);
        payload.append('sender_name', form.sender_name);
        payload.append('transfer_date', form.transfer_date);
        payload.append('referral_source', form.referral_source);
        payload.append('agent_name', form.agent_name);
        payload.append(
            'confirmation_checked',
            form.confirmation_checked ? '1' : '0',
        );
        payload.append('captcha_token', form.captcha_token);

        form.deceased_names.forEach((item, index) => {
            payload.append(
                `deceased_names[${index}][indonesian_name]`,
                item.indonesian_name,
            );
            payload.append(
                `deceased_names[${index}][mandarin_name]`,
                item.mandarin_name,
            );

            if (item.source_image) {
                payload.append(
                    `deceased_names[${index}][source_image]`,
                    item.source_image,
                );
            }
        });

        payload.append(
            'incense_name[indonesian_name]',
            form.incense_name.indonesian_name,
        );
        payload.append(
            'incense_name[mandarin_name]',
            form.incense_name.mandarin_name,
        );

        if (form.incense_name.source_image) {
            payload.append(
                'incense_name[source_image]',
                form.incense_name.source_image,
            );
        }

        if (form.proof) {
            payload.append('proof', form.proof);
        }

        try {
            const response = await fetch('/api/public/bookings', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
            });

            if (response.status === 422) {
                const data = (await response.json()) as {
                    errors?: Record<string, string[]>;
                    message?: string;
                };
                const nextErrors = Object.fromEntries(
                    Object.entries(data.errors ?? {}).map(([key, value]) => [
                        key,
                        value[0] ?? 'Data belum benar.',
                    ]),
                );
                setErrors(nextErrors);
                setGeneralError(
                    data.message ?? 'Beberapa data masih perlu diperbaiki.',
                );
                setStep(getStepFromErrors(nextErrors));

                return;
            }

            if (response.status === 429) {
                setGeneralError(
                    'Terlalu banyak percobaan. Silakan tunggu sebentar lalu coba lagi.',
                );

                return;
            }

            if (!response.ok) {
                setGeneralError(
                    'Booking belum berhasil dikirim. Silakan coba lagi.',
                );

                return;
            }

            const data = (await response.json()) as { success_url: string };
            window.location.href = data.success_url;
        } finally {
            setProcessing(false);
        }
    };

    /* ── Derived values ── */
    const currentMealTotal =
        Number(form.vegetarian_quantity || 0) +
        Number(form.non_vegetarian_quantity || 0);
    const prayerPreviewNames = form.deceased_names
        .map((entry) => pickPrayerName(entry))
        .filter(
            (entry): entry is { text: string; vertical: boolean } =>
                entry !== null,
        )
        .slice(0, 2);
    const incensePreviewName = pickPrayerName(form.incense_name);
    const prayerPreviewImageUrls = form.deceased_names
        .slice(0, 2)
        .map((entry) =>
            buildPreviewImageUrl(preview.render_url, 'A', 1, pickPrayerName(entry), {
                indonesian: entry.indonesian_name,
                mandarin: entry.mandarin_name,
            }),
        );
    const incensePreviewImageUrl = buildPreviewImageUrl(
        preview.render_url,
        'B',
        1,
        incensePreviewName,
        {
            indonesian: form.incense_name.indonesian_name,
            mandarin: form.incense_name.mandarin_name,
        },
    );
    const closeFlyerModal = () => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(BOOKING_FLYER_DISMISS_KEY, '1');
        }

        setShowFlyerModal(false);
    };

    /* ── Render ─────────────────────────────────────────────────────────────── */
    return (
        <>
            <Head title="Booking" />

            {showFlyerModal ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
                    <button
                        type="button"
                        aria-label="Tutup flyer"
                        onClick={closeFlyerModal}
                        className="absolute inset-0 bg-black/70"
                    />

                    <div className="relative z-10 w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl">
                        <button
                            type="button"
                            onClick={closeFlyerModal}
                            aria-label="Tutup flyer"
                            className="absolute right-3 top-3 z-20 flex h-11 w-11 items-center justify-center rounded-full bg-black/70 text-2xl leading-none text-white transition hover:bg-black"
                        >
                            ×
                        </button>

                        <div className="max-h-[85vh] overflow-y-auto bg-[#2C1810]">
                            <img
                                src={BOOKING_FLYER_SRC}
                                alt="Flyer Chao Du"
                                className="block h-auto w-full"
                            />
                        </div>
                    </div>
                </div>
            ) : null}

            <main className="min-h-screen bg-[#FDF8F0]">
                {/* ── Header ── */}
                <div className="bg-[#FDF8F0]">
                    <div className="w-full overflow-hidden">
                        {headerBannerMissing ? (
                            <div className="flex aspect-[3/1] w-full items-center justify-center bg-[#8B1A1A] px-6 text-center text-base font-medium text-white/85">
                                Banner acara akan tampil di sini
                            </div>
                        ) : (
                            <img
                                src={headerBannerUrl}
                                alt="Banner Chao Du"
                                className="block w-full object-contain"
                                onError={() => setHeaderBannerMissing(true)}
                            />
                        )}
                    </div>
                    {/* Gold divider */}
                    <div className="h-[3px] bg-gradient-to-r from-[#B8860B] via-[#F5D061] to-[#B8860B]" />
                </div>

                <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                    <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                        <form onSubmit={submit} className="space-y-5">
                            <div className="flex justify-end">
                                <Link
                                    href="/masuk"
                                    className="rounded-full border-2 border-[#E8D5C0] bg-white px-4 py-2 text-sm font-semibold text-[#8B1A1A] transition hover:border-[#8B1A1A] hover:bg-[#FDF8F0]"
                                >
                                    Masuk petugas
                                </Link>
                            </div>
                            {/* ── Step indicator ── */}
                            <div className="rounded-2xl border border-[#E8D5C0] bg-white p-4 shadow-sm sm:p-5">
                                <div className="flex items-center justify-between">
                                    {steps.map((label, index) => {
                                        const number = index + 1;
                                        const active = step === number;
                                        const done = step > number;
                                        const [lineOne, lineTwo] =
                                            label.split('\n');

                                        return (
                                            <div
                                                key={label}
                                                className="relative flex flex-1 flex-col items-center gap-1"
                                            >
                                                {/* Connector line */}
                                                {index < steps.length - 1 && (
                                                    <div
                                                        className={[
                                                            'absolute top-4 right-[calc(-50%+16px)] left-[calc(50%+16px)] h-0.5',
                                                            done
                                                                ? 'bg-[#D4A017]'
                                                                : 'bg-[#E8D5C0]',
                                                        ].join(' ')}
                                                    />
                                                )}
                                                {/* Dot */}
                                                <div
                                                    className={[
                                                        'relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold transition',
                                                        active
                                                            ? 'bg-[#8B1A1A] text-white'
                                                            : done
                                                              ? 'bg-[#D4A017] text-white'
                                                              : 'bg-[#F0E8DC] text-[#5C3D2E]',
                                                    ].join(' ')}
                                                >
                                                    {done ? '✓' : number}
                                                </div>
                                                {/* Label */}
                                                <span
                                                    className={[
                                                        'text-center text-[11px] leading-tight',
                                                        active
                                                            ? 'font-semibold text-[#8B1A1A]'
                                                            : done
                                                              ? 'text-[#B8860B]'
                                                              : 'text-[#5C3D2E]/60',
                                                    ].join(' ')}
                                                >
                                                    {lineOne}
                                                    <br />
                                                    {lineTwo}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* ── General error ── */}
                            {generalError && (
                                <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
                                    {generalError}
                                </div>
                            )}

                            {/* ── Step 1: Identitas ── */}
                            {step === 1 && (
                                <section className="rounded-2xl border border-[#E8D5C0] bg-white p-5 shadow-sm sm:p-6">
                                    <h2 className="text-2xl font-semibold text-[#2C1810]">
                                        Identitas pemesan
                                    </h2>
                                    <p className="mt-2 text-sm leading-6 text-[#5C3D2E]">
                                        Isi data diri Anda dengan lengkap dan
                                        benar.
                                    </p>

                                    <div className="mt-5 grid gap-5">
                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Nama lengkap
                                            </span>
                                            <input
                                                aria-label="Nama pemesan"
                                                type="text"
                                                value={form.customer_name}
                                                onChange={(e) =>
                                                    setField(
                                                        'customer_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Contoh: Budi Santoso"
                                                className={inputCls}
                                            />
                                            <ErrorText
                                                value={errors.customer_name}
                                            />
                                        </label>

                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Nomor telepon
                                            </span>
                                            <div className="flex overflow-hidden rounded-xl border-2 border-[#E8D5C0] bg-white focus-within:border-[#8B1A1A]">
                                                <span className="flex items-center bg-[#F5EDE4] px-4 text-base font-medium text-[#5C3D2E]">
                                                    +62
                                                </span>
                                                <input
                                                    aria-label="Nomor telepon"
                                                    type="text"
                                                    inputMode="numeric"
                                                    value={
                                                        form.customer_phone_local
                                                    }
                                                    onChange={(e) =>
                                                        setField(
                                                            'customer_phone_local',
                                                            onlyDigits(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    placeholder="81234567890"
                                                    className="min-w-0 flex-1 bg-white px-4 py-3 text-base text-[#2C1810] outline-none"
                                                />
                                            </div>
                                            <ErrorText
                                                value={
                                                    errors.customer_phone_local
                                                }
                                            />
                                        </label>

                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Email
                                            </span>
                                            <input
                                                aria-label="Email"
                                                type="email"
                                                value={form.customer_email}
                                                onChange={(e) =>
                                                    setField(
                                                        'customer_email',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="nama@email.com"
                                                className={inputCls}
                                            />
                                            <ErrorText
                                                value={errors.customer_email}
                                            />
                                        </label>

                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Jumlah yang hadir
                                            </span>
                                            <input
                                                aria-label="Jumlah yang hadir"
                                                type="number"
                                                min={1}
                                                value={form.attendee_count}
                                                onChange={(e) =>
                                                    setField(
                                                        'attendee_count',
                                                        onlyDigits(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                placeholder="Contoh: 4"
                                                className={inputCls}
                                            />
                                            <ErrorText
                                                value={errors.attendee_count}
                                            />
                                        </label>
                                    </div>
                                </section>
                            )}

                            {/* ── Step 2: Pilih Paket ── */}
                            {step === 2 && (
                                <section className="rounded-2xl border border-[#E8D5C0] bg-white p-5 shadow-sm sm:p-6">
                                    <h2 className="text-2xl font-semibold text-[#2C1810]">
                                        Pilih paket
                                    </h2>
                                    <p className="mt-2 text-sm leading-6 text-[#5C3D2E]">
                                        Ketuk kartu untuk memilih paket yang
                                        sesuai.
                                    </p>

                                    <div className="mt-5 grid gap-4 xl:grid-cols-3">
                                        {packages.map((item) => (
                                            <PackageCard
                                                key={item.code}
                                                item={item}
                                                selected={
                                                    form.package_code ===
                                                    item.code
                                                }
                                                disabled={
                                                    reservingVirtualAccount
                                                }
                                                onSelect={() =>
                                                    choosePackage(item)
                                                }
                                            />
                                        ))}
                                    </div>
                                    <ErrorText value={errors.package_code} />

                                    {(selectedPackage?.code === 'PRAYER' ||
                                        selectedPackage?.code === 'COMBO') && (
                                        <div className="mt-6 rounded-xl bg-[#FDF6DC] p-5">
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <h3 className="text-lg font-semibold text-[#2C1810]">
                                                        Nama Almarhum/ah
                                                    </h3>
                                                    <p className="mt-1 text-sm leading-6 text-[#5C3D2E]">
                                                        Anda bisa isi langsung,
                                                        atau unggah foto agar
                                                        tulisan dibaca otomatis.
                                                    </p>
                                                </div>
                                                <p className="max-w-sm text-sm leading-6 text-[#5C3D2E]">
                                                    Periksa kembali penulisan mandarin di
                                                    samping sebelum lanjut ke
                                                    langkah berikutnya.
                                                </p>
                                            </div>
                                            <div className="mt-4 space-y-4">
                                                {form.deceased_names.map(
                                                    (item, index) => (
                                                        <NameCard
                                                            key={index}
                                                            title={`Data Almarhum/ah ${index + 1}`}
                                                            indonesianLabel={`Nama Indonesia ${index + 1}`}
                                                            mandarinLabel={`Nama Mandarin ${index + 1}`}
                                                            photoLabel={`Foto nama ${index + 1}`}
                                                            readButtonLabel={`Baca foto nama ${index + 1}`}
                                                            entry={item}
                                                            mandarinError={
                                                                errors[
                                                                    `deceased_names.${index}.mandarin_name`
                                                                ]
                                                            }
                                                            photoError={
                                                                errors[
                                                                    `deceased_names.${index}.source_image`
                                                                ]
                                                            }
                                                            maxUploadMb={
                                                                limits.ocr_upload_max_mb
                                                            }
                                                            indonesianLocked={
                                                                item.mandarin_name.trim() !== '' ||
                                                                item.source_image !== null
                                                            }
                                                            mandarinLocked={
                                                                item.indonesian_name.trim() !== ''
                                                            }
                                                            onChangeName={(
                                                                key,
                                                                value,
                                                            ) =>
                                                                setDeceasedName(
                                                                    index,
                                                                    key,
                                                                    value,
                                                                )
                                                            }
                                                            onChangePhoto={(
                                                                file,
                                                            ) =>
                                                                setDeceasedSourceImage(
                                                                    index,
                                                                    file,
                                                                )
                                                            }
                                                            onReadPhoto={() =>
                                                                readPhoto(
                                                                    'deceased',
                                                                    index,
                                                                )
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                            <ErrorText
                                                value={errors.deceased_names}
                                            />
                                        </div>
                                    )}

                                    {(selectedPackage?.code === 'INCENSE' ||
                                        selectedPackage?.code === 'COMBO') && (
                                        <div className="mt-6 rounded-xl bg-[#FDF6DC] p-5">
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <h3 className="text-lg font-semibold text-[#2C1810]">
                                                        Nama Orang atau Keluarga
                                                        yang Ingin Didoakan
                                                    </h3>
                                                    <p className="mt-1 text-sm leading-6 text-[#5C3D2E]">
                                                        Hio dipakai untuk doa
                                                        bagi orang yang masih
                                                        hidup. Isi nama orang
                                                        atau keluarga yang ingin
                                                        didoakan.
                                                    </p>
                                                </div>
                                                <p className="max-w-sm text-sm leading-6 text-[#5C3D2E]">
                                                    Periksa contoh kertas di
                                                    samping sebelum lanjut ke
                                                    langkah berikutnya.
                                                </p>
                                            </div>
                                            <div className="mt-4">
                                                <NameCard
                                                    title="Data nama yang didoakan"
                                                    indonesianLabel="Nama Indonesia yang didoakan"
                                                    mandarinLabel="Nama Mandarin yang didoakan"
                                                    photoLabel="Foto nama yang didoakan"
                                                    readButtonLabel="Baca foto nama yang didoakan"
                                                    entry={form.incense_name}
                                                    multiline
                                                    mandarinError={
                                                        errors[
                                                            'incense_name.mandarin_name'
                                                        ]
                                                    }
                                                    photoError={
                                                        errors[
                                                            'incense_name.source_image'
                                                        ]
                                                    }
                                                    maxUploadMb={
                                                        limits.ocr_upload_max_mb
                                                    }
                                                    indonesianLocked={
                                                        form.incense_name.mandarin_name.trim() !== '' ||
                                                        form.incense_name.source_image !== null
                                                    }
                                                    mandarinLocked={
                                                        form.incense_name.indonesian_name.trim() !== ''
                                                    }
                                                    onChangeName={
                                                        setIncenseName
                                                    }
                                                    onChangePhoto={
                                                        setIncenseSourceImage
                                                    }
                                                    onReadPhoto={() =>
                                                        readPhoto('incense')
                                                    }
                                                />
                                            </div>
                                            <ErrorText
                                                value={errors.incense_name}
                                            />
                                        </div>
                                    )}
                                </section>
                            )}

                            {/* ── Step 3: Makanan ── */}
                            {step === 3 && (
                                <section className="rounded-2xl border border-[#E8D5C0] bg-white p-5 shadow-sm sm:p-6">
                                    <h2 className="text-2xl font-semibold text-[#2C1810]">
                                        Pilihan makanan
                                    </h2>
                                    <p className="mt-2 text-sm leading-6 text-[#5C3D2E]">
                                        {selectedPackage
                                            ? `Total maksimal ${selectedPackage.meal_quota} porsi.`
                                            : 'Pilih paket terlebih dahulu.'}
                                    </p>

                                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Vegetarian
                                            </span>
                                            <input
                                                type="number"
                                                min={0}
                                                step={1}
                                                value={form.vegetarian_quantity}
                                                onChange={(e) =>
                                                    setField(
                                                        'vegetarian_quantity',
                                                        onlyDigits(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                className={inputCls}
                                            />
                                        </label>
                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Non-vegetarian
                                            </span>
                                            <input
                                                type="number"
                                                min={0}
                                                step={1}
                                                value={
                                                    form.non_vegetarian_quantity
                                                }
                                                onChange={(e) =>
                                                    setField(
                                                        'non_vegetarian_quantity',
                                                        onlyDigits(
                                                            e.target.value,
                                                        ),
                                                    )
                                                }
                                                className={inputCls}
                                            />
                                        </label>
                                    </div>

                                    <div className="mt-4 rounded-xl border border-[#E8D084] bg-[#FDF6DC] px-4 py-3">
                                        <p className="text-sm text-[#5C3D2E]">
                                            Total saat ini:{' '}
                                            <span className="font-semibold text-[#B8860B]">
                                                {currentMealTotal} porsi
                                            </span>
                                            {selectedPackage
                                                ? ` dari maksimal ${selectedPackage.meal_quota} porsi.`
                                                : '. Boleh diisi 0.'}
                                        </p>
                                    </div>
                                    <ErrorText
                                        value={errors.vegetarian_quantity}
                                    />
                                    <ErrorText
                                        value={errors.non_vegetarian_quantity}
                                    />
                                </section>
                            )}

                            {/* ── Step 4: Pembayaran ── */}
                            {step === 4 && (
                                <section className="rounded-2xl border border-[#E8D5C0] bg-white p-5 shadow-sm sm:p-6">
                                    <h2 className="text-2xl font-semibold text-[#2C1810]">
                                        Pembayaran
                                    </h2>
                                    <p className="mt-2 text-sm leading-6 text-[#5C3D2E]">
                                        Gunakan nomor VA berikut, lalu isi data
                                        konfirmasi di bawah.
                                    </p>

                                    {/* Bank info box */}
                                    <div className="mt-5 rounded-xl border border-red-200 bg-[#FDF8F0] p-5">
                                        <p className="text-sm text-[#8B1A1A]">
                                            Total yang harus dibayar
                                        </p>
                                        <p className="mt-1 text-3xl font-semibold text-[#6B1414]">
                                            {selectedPackage
                                                ? formatCurrency(
                                                      selectedPackage.price,
                                                  )
                                                : 'Pilih paket terlebih dahulu'}
                                        </p>
                                        <button
                                            type="button"
                                            onClick={copyPaymentAmount}
                                            disabled={!selectedPackage}
                                            className="mt-3 flex items-center gap-2 rounded-full border-2 border-[#8B1A1A] px-4 py-2 text-sm font-semibold text-[#8B1A1A] transition hover:bg-[#FDF8F0] disabled:opacity-50"
                                        >
                                            {copiedAmount
                                                ? 'Nominal sudah tersalin'
                                                : 'Salin nominal bayar'}
                                        </button>
                                        <div className="mt-4 rounded-xl border border-[#E8D084] bg-[#FFF7D6] px-4 py-4 text-sm leading-7 text-[#5C3D2E]">
                                            <p className="font-semibold text-[#2C1810]">
                                                Catatan penting saat transfer
                                            </p>
                                            <p className="mt-1">
                                                Pastikan menuliskan nama paket
                                                yang diambil saat transfer,
                                                supaya lebih mudah dicek oleh
                                                petugas.
                                            </p>
                                        </div>
                                        <div className="mt-4 space-y-1 text-sm text-[#5C3D2E]">
                                            <p>
                                                Bank:{' '}
                                                {payment.bank_name ??
                                                    'Belum diatur'}
                                            </p>
                                            <p>
                                                Nomor VA:{' '}
                                                <span className="font-semibold text-[#2C1810]">
                                                    {packageAccountNumber ??
                                                        'Belum diatur'}
                                                </span>
                                            </p>
                                            <p>
                                                Atas nama:{' '}
                                                {payment.bank_account_holder ??
                                                    'Belum diatur'}
                                            </p>
                                            {isPoolVirtualAccount ? (
                                                <p>
                                                    Batas waktu:{' '}
                                                    <span className="font-semibold text-[#2C1810]">
                                                        {typeof reservationRemainingSeconds ===
                                                        'number'
                                                            ? formatRemainingTime(
                                                                  reservationRemainingSeconds,
                                                              )
                                                            : `${payment.hold_minutes} menit`}
                                                    </span>
                                                </p>
                                            ) : null}
                                        </div>
                                        {reservationExpired ? (
                                            <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                                Nomor pembayaran sudah lewat
                                                waktu. Silakan kembali ke
                                                pilihan paket.
                                            </p>
                                        ) : null}
                                        <button
                                            type="button"
                                            onClick={copyAccountNumber}
                                            disabled={!packageAccountNumber}
                                            className="mt-4 flex items-center gap-2 rounded-full border-2 border-[#8B1A1A] px-4 py-2 text-sm font-semibold text-[#8B1A1A] transition hover:bg-[#FDF8F0] disabled:opacity-50"
                                        >
                                            {copiedAccountNumber
                                                ? 'Sudah tersalin'
                                                : 'Salin nomor VA'}
                                        </button>
                                    </div>

                                    <div className="mt-5 grid gap-5">
                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Nama pengirim
                                            </span>
                                            <input
                                                type="text"
                                                value={form.sender_name}
                                                onChange={(e) =>
                                                    setField(
                                                        'sender_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Nama sesuai rekening pengirim"
                                                className={inputCls}
                                            />
                                            <ErrorText
                                                value={errors.sender_name}
                                            />
                                        </label>

                                        <div className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Jumlah bayar
                                            </span>
                                            <div className="rounded-2xl border border-[#D8C8B5] bg-[#F9F3EA] px-4 py-3 text-base text-[#2C1810]">
                                                {selectedPackage
                                                    ? formatCurrency(
                                                          selectedPackage.price,
                                                      )
                                                    : '-'}
                                            </div>
                                        </div>

                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Tanggal transfer
                                            </span>
                                            <input
                                                type="date"
                                                value={form.transfer_date}
                                                onChange={(e) =>
                                                    setField(
                                                        'transfer_date',
                                                        e.target.value,
                                                    )
                                                }
                                                className={inputCls}
                                            />
                                            <ErrorText
                                                value={errors.transfer_date}
                                            />
                                        </label>

                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Bukti transfer
                                            </span>
                                            <input
                                                type="file"
                                                accept=".jpg,.jpeg,.png,.pdf"
                                                onChange={(
                                                    e: ChangeEvent<HTMLInputElement>,
                                                ) =>
                                                    setField(
                                                        'proof',
                                                        e.target.files?.[0] ??
                                                            null,
                                                    )
                                                }
                                                className="block w-full text-sm text-[#5C3D2E]"
                                            />
                                            <p className="mt-2 text-sm text-[#5C3D2E]">
                                                Format JPG, PNG, atau PDF.
                                                Maksimal {limits.upload_max_mb}{' '}
                                                MB.
                                            </p>
                                            {form.proof && (
                                                <p className="mt-2 text-sm text-[#5C3D2E]">
                                                    {form.proof.name}
                                                </p>
                                            )}
                                            <ErrorText value={errors.proof} />
                                        </label>
                                    </div>
                                </section>
                            )}

                            {/* ── Step 5: Info Tambahan ── */}
                            {step === 5 && (
                                <section className="rounded-2xl border border-[#E8D5C0] bg-white p-5 shadow-sm sm:p-6">
                                    <h2 className="text-2xl font-semibold text-[#2C1810]">
                                        Informasi tambahan
                                    </h2>
                                    <p className="mt-2 text-sm leading-6 text-[#5C3D2E]">
                                        Hanya satu pertanyaan singkat.
                                    </p>

                                    <div className="mt-5 grid gap-5">
                                        <label className="block">
                                            <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                Dari mana Anda mengetahui acara
                                                ini?
                                            </span>
                                            <select
                                                value={form.referral_source}
                                                onChange={(e) =>
                                                    setField(
                                                        'referral_source',
                                                        e.target
                                                            .value as FormState['referral_source'],
                                                    )
                                                }
                                                className={inputCls}
                                            >
                                                <option value="">
                                                    — Pilih salah satu —
                                                </option>
                                                <option value="TEMAN">
                                                    Teman
                                                </option>
                                                <option value="KELUARGA">
                                                    Keluarga
                                                </option>
                                                <option value="MEDIA_SOSIAL">
                                                    Media sosial
                                                </option>
                                                <option value="WEBSITE">
                                                    Website
                                                </option>
                                                <option value="AGENT">
                                                    Agent
                                                </option>
                                            </select>
                                            <ErrorText
                                                value={errors.referral_source}
                                            />
                                        </label>

                                        {form.referral_source === 'AGENT' && (
                                            <label className="block">
                                                <span className="mb-2 block text-base font-medium text-[#2C1810]">
                                                    Nama lengkap agent
                                                </span>
                                                <input
                                                    type="text"
                                                    value={form.agent_name}
                                                    onChange={(e) =>
                                                        setField(
                                                            'agent_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className={inputCls}
                                                />
                                                <ErrorText
                                                    value={errors.agent_name}
                                                />
                                            </label>
                                        )}
                                    </div>
                                </section>
                            )}

                            {/* ── Step 6: Periksa Ulang ── */}
                            {step === 6 && (
                                <section className="rounded-2xl border border-[#E8D5C0] bg-white p-5 shadow-sm sm:p-6">
                                    <h2 className="text-2xl font-semibold text-[#2C1810]">
                                        Periksa kembali
                                    </h2>
                                    <p className="mt-2 text-sm leading-6 text-[#5C3D2E]">
                                        Pastikan semua data sudah benar sebelum
                                        mengirim.
                                    </p>

                                    <div className="mt-5 divide-y divide-[#E8D5C0]">
                                        {/* Identitas */}
                                        <div className="py-4 first:pt-0">
                                            <p className="mb-2 text-xs font-semibold tracking-widest text-[#B8860B] uppercase">
                                                Identitas pemesan
                                            </p>
                                            <p className="text-base leading-7 text-[#2C1810]">
                                                {form.customer_name || '-'}
                                                <br />
                                                {form.customer_phone_local
                                                    ? `+62${form.customer_phone_local}`
                                                    : '-'}
                                                <br />
                                                {form.customer_email || '-'}
                                                <br />
                                                {form.attendee_count ||
                                                    '-'}{' '}
                                                orang
                                            </p>
                                        </div>

                                        {/* Paket */}
                                        <div className="py-4">
                                            <p className="mb-2 text-xs font-semibold tracking-widest text-[#B8860B] uppercase">
                                                Paket
                                            </p>
                                            <p className="text-base leading-7 text-[#2C1810]">
                                                {selectedPackage?.name ?? '-'}
                                                <br />
                                                {selectedPackage
                                                    ? formatCurrency(
                                                          selectedPackage.price,
                                                      )
                                                    : '-'}
                                            </p>
                                        </div>

                                        {/* Nama */}
                                        <div className="py-4">
                                            <p className="mb-2 text-xs font-semibold tracking-widest text-[#B8860B] uppercase">
                                                Nama
                                            </p>
                                            <div className="text-base leading-7 text-[#2C1810]">
                                                {form.deceased_names
                                                    .filter(
                                                        (item) =>
                                                            item.indonesian_name ||
                                                            item.mandarin_name,
                                                    )
                                                    .map((item, index) => (
                                                        <p key={index}>
                                                            {item.indonesian_name ||
                                                                '-'}{' '}
                                                            /{' '}
                                                            {item.mandarin_name ||
                                                                '-'}
                                                        </p>
                                                    ))}
                                                {(form.incense_name
                                                    .indonesian_name ||
                                                    form.incense_name
                                                        .mandarin_name) && (
                                                    <p>
                                                        {form.incense_name
                                                            .indonesian_name ||
                                                            '-'}{' '}
                                                        /{' '}
                                                        {form.incense_name
                                                            .mandarin_name ||
                                                            '-'}
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        {/* Makanan */}
                                        <div className="py-4">
                                            <p className="mb-2 text-xs font-semibold tracking-widest text-[#B8860B] uppercase">
                                                Makanan
                                            </p>
                                            <p className="text-base text-[#2C1810]">
                                                {form.vegetarian_quantity || 0}{' '}
                                                vegetarian dan{' '}
                                                {form.non_vegetarian_quantity ||
                                                    0}{' '}
                                                non-vegetarian
                                            </p>
                                        </div>

                                        {/* Pembayaran */}
                                        <div className="py-4">
                                            <p className="mb-2 text-xs font-semibold tracking-widest text-[#B8860B] uppercase">
                                                Pembayaran
                                            </p>
                                            <p className="text-base leading-7 text-[#2C1810]">
                                                {form.sender_name || '-'}
                                                <br />
                                                {selectedPackage
                                                    ? formatCurrency(
                                                          selectedPackage.price,
                                                      )
                                                    : '-'}
                                                <br />
                                                {form.transfer_date || '-'}
                                                <br />
                                                {form.proof?.name ?? '-'}
                                            </p>
                                        </div>

                                        {/* Info tambahan */}
                                        <div className="py-4 last:pb-0">
                                            <p className="mb-2 text-xs font-semibold tracking-widest text-[#B8860B] uppercase">
                                                Informasi tambahan
                                            </p>
                                            <p className="text-base text-[#2C1810]">
                                                {labelReferralSource(
                                                    form.referral_source,
                                                )}
                                                {form.referral_source ===
                                                    'AGENT' && (
                                                    <>
                                                        {' '}
                                                        —{' '}
                                                        {form.agent_name || '-'}
                                                    </>
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Konfirmasi */}
                                    <label className="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border border-[#E8D084] bg-[#FDF6DC] px-4 py-4">
                                        <input
                                            type="checkbox"
                                            checked={form.confirmation_checked}
                                            onChange={(e) =>
                                                setField(
                                                    'confirmation_checked',
                                                    e.target.checked,
                                                )
                                            }
                                            className="mt-1 h-5 w-5 accent-[#8B1A1A]"
                                        />
                                        <span className="text-base leading-6 text-[#2C1810]">
                                            Saya sudah memeriksa kembali semua
                                            data dan tulisan pada contoh kertas.
                                        </span>
                                    </label>
                                    <ErrorText
                                        value={errors.confirmation_checked}
                                    />

                                    {captcha.enabled && (
                                        <div className="mt-5">
                                            {captcha.site_key ? (
                                                <div
                                                    ref={captchaContainerRef}
                                                />
                                            ) : (
                                                <p className="text-sm text-red-700">
                                                    Pemeriksaan keamanan sedang
                                                    belum tersedia.
                                                </p>
                                            )}
                                            <ErrorText
                                                value={errors.captcha_token}
                                            />
                                        </div>
                                    )}
                                </section>
                            )}

                            {/* ── Navigation buttons ── */}
                            <div className="flex items-center justify-between gap-3">
                                    <button
                                        type="button"
                                        onClick={previousStep}
                                    disabled={
                                        step === 1 ||
                                        processing ||
                                        reservingVirtualAccount
                                    }
                                        className="rounded-full border-2 border-[#E8D5C0] px-6 py-3 text-base font-semibold text-[#5C3D2E] transition hover:border-[#5C3D2E] disabled:opacity-40"
                                    >
                                    ← Kembali
                                </button>

                                {step < steps.length ? (
                                    <button
                                        type="button"
                                        onClick={nextStep}
                                        disabled={
                                            processing ||
                                            reservingVirtualAccount
                                        }
                                        className="rounded-full bg-[#8B1A1A] px-8 py-3 text-base font-semibold text-white transition hover:bg-[#6B1414]"
                                    >
                                        {reservingVirtualAccount
                                            ? 'Menyiapkan nomor VA...'
                                            : 'Lanjut'}
                                    </button>
                                ) : (
                                    <button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            (captcha.enabled &&
                                                !captcha.site_key)
                                        }
                                        className="rounded-full bg-[#D4A017] px-8 py-3 text-base font-semibold text-white transition hover:bg-[#B8860B] disabled:opacity-50"
                                    >
                                        {processing
                                            ? 'Mengirim...'
                                            : 'Kirim booking'}
                                    </button>
                                )}
                            </div>
                        </form>

                        {/* ── Sidebar preview ── */}
                        <aside className="space-y-5">
                            {(selectedPackage?.code === 'PRAYER' ||
                                selectedPackage?.code === 'COMBO') && (
                                <>
                                    {prayerPreviewNames.map((name, index) => (
                                        <PrayerPaperPreview
                                            key={`prayer-preview-${index}`}
                                            template={{
                                                ...preview.prayer,
                                                title:
                                                    prayerPreviewNames.length >
                                                    1
                                                        ? `${preview.prayer.title} ${index + 1}`
                                                        : preview.prayer.title,
                                            }}
                                            name={name}
                                            imageUrl={
                                                prayerPreviewImageUrls[index] ??
                                                null
                                            }
                                            kind="prayer"
                                        />
                                    ))}
                                </>
                            )}

                            {(selectedPackage?.code === 'INCENSE' ||
                                selectedPackage?.code === 'COMBO') && (
                                <PrayerPaperPreview
                                    template={preview.incense}
                                    name={incensePreviewName}
                                    imageUrl={incensePreviewImageUrl}
                                    kind="incense"
                                />
                            )}
                        </aside>
                    </div>
                </div>
            </main>
        </>
    );
}
