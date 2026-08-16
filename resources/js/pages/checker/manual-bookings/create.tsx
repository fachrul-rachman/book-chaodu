import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

type PackageCode = 'PRAYER' | 'INCENSE' | 'COMBO';
type NameEntry = {
    position: number;
    indonesian_name: string;
    mandarin_name: string;
};
type Props = {
    packages: Array<{ code: PackageCode; name: string }>;
    table_slots: Array<{ id: number; code: string }>;
    incense_slots: Array<{ id: number; number: number }>;
    ocr: { url: string; max_mb: number };
};

const inputClass =
    'w-full rounded-2xl border border-[var(--color-border)] bg-white px-4 py-3 text-base';

function idempotencyKey(): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `checker-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export default function CheckerManualBookingCreatePage() {
    const { packages, table_slots, incense_slots, ocr } =
        usePage<Props>().props;
    const form = useForm({
        idempotency_key: idempotencyKey(),
        customer_name: '',
        customer_phone_local: '',
        customer_email: '',
        referral_source: '' as '' | 'WEBSITE' | 'AGENT',
        agent_name: '',
        package_code: '' as '' | PackageCode,
        table_slot_id: '',
        incense_slot_id: '',
        deceased_names: [
            { position: 1, indonesian_name: '', mandarin_name: '' },
            { position: 2, indonesian_name: '', mandarin_name: '' },
        ] as NameEntry[],
        incense_name: {
            position: 1,
            indonesian_name: '',
            mandarin_name: '',
        } as NameEntry,
    });
    const [ocrStatus, setOcrStatus] = useState<Record<string, string>>({});
    const prayerFileRefs = [
        useRef<HTMLInputElement>(null),
        useRef<HTMLInputElement>(null),
    ];
    const incenseFileRef = useRef<HTMLInputElement>(null);
    const needsTable =
        form.data.package_code === 'PRAYER' ||
        form.data.package_code === 'COMBO';
    const needsIncense =
        form.data.package_code === 'INCENSE' ||
        form.data.package_code === 'COMBO';

    const updatePrayerName = (
        index: number,
        field: 'indonesian_name' | 'mandarin_name',
        value: string,
    ) => {
        const names = form.data.deceased_names.map((name, itemIndex) =>
            itemIndex === index ? { ...name, [field]: value } : name,
        );
        form.setData('deceased_names', names);
    };

    const readPhoto = async (
        file: File | undefined,
        key: string,
        onText: (text: string) => void,
    ) => {
        if (!file) {
            return;
        }

        if (file.size > ocr.max_mb * 1024 * 1024) {
            setOcrStatus((current) => ({
                ...current,
                [key]: `Ukuran foto maksimal ${ocr.max_mb} MB.`,
            }));

            return;
        }

        setOcrStatus((current) => ({ ...current, [key]: 'Membaca foto...' }));
        const body = new FormData();
        body.append('source_image', file);

        try {
            const response = await fetch(ocr.url, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body,
            });
            const result = (await response.json()) as {
                text?: string;
                message?: string;
            };

            if (!response.ok || !result.text) {
                throw new Error(result.message || 'Foto belum bisa dibaca.');
            }

            onText(result.text);
            setOcrStatus((current) => ({
                ...current,
                [key]: 'Foto berhasil dibaca. Periksa kembali hasilnya.',
            }));
        } catch (error) {
            setOcrStatus((current) => ({
                ...current,
                [key]:
                    error instanceof Error
                        ? error.message
                        : 'Foto belum bisa dibaca.',
            }));
        }
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/checker/daftar-manual');
    };

    return (
        <>
            <Head title="Daftar Manual" />
            <main className="min-h-screen px-4 py-8 sm:px-6">
                <div className="mx-auto max-w-3xl space-y-6">
                    <header className="flex flex-col gap-4 rounded-[24px] border border-[var(--color-border)] bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                Checker
                            </p>
                            <h1 className="mt-1 text-3xl font-semibold">
                                Daftar manual
                            </h1>
                            <p className="mt-2 text-sm leading-6 text-slate-700">
                                Booking langsung disetujui. Pastikan nomor meja
                                dan hio sudah benar sebelum menyimpan.
                            </p>
                        </div>
                        <Link
                            href="/checker"
                            className="rounded-full border border-[var(--color-brand)] px-5 py-3 text-center text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </header>

                    <form
                        onSubmit={submit}
                        className="space-y-6 rounded-[24px] border border-[var(--color-border)] bg-white p-6 shadow-sm sm:p-8"
                    >
                        <section className="grid gap-4 sm:grid-cols-2">
                            <label className="sm:col-span-2">
                                <span className="mb-2 block text-sm font-medium">
                                    Nama customer
                                </span>
                                <input
                                    aria-label="Nama customer"
                                    className={inputClass}
                                    value={form.data.customer_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'customer_name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label>
                                <span className="mb-2 block text-sm font-medium">
                                    Email
                                </span>
                                <input
                                    aria-label="Email"
                                    type="email"
                                    className={inputClass}
                                    value={form.data.customer_email}
                                    onChange={(event) =>
                                        form.setData(
                                            'customer_email',
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label>
                                <span className="mb-2 block text-sm font-medium">
                                    Nomor telepon
                                </span>
                                <div className="flex items-center rounded-2xl border border-[var(--color-border)] bg-white">
                                    <span className="px-4 text-slate-600">
                                        +62
                                    </span>
                                    <input
                                        aria-label="Nomor telepon"
                                        type="tel"
                                        inputMode="numeric"
                                        className="min-w-0 flex-1 rounded-r-2xl px-3 py-3 text-base outline-none"
                                        value={form.data.customer_phone_local}
                                        onChange={(event) =>
                                            form.setData(
                                                'customer_phone_local',
                                                event.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                    />
                                </div>
                            </label>
                            <label
                                className={
                                    form.data.referral_source === 'AGENT'
                                        ? ''
                                        : 'sm:col-span-2'
                                }
                            >
                                <span className="mb-2 block text-sm font-medium">
                                    Sumber
                                </span>
                                <select
                                    aria-label="Sumber"
                                    className={inputClass}
                                    value={form.data.referral_source}
                                    onChange={(event) => {
                                        const source = event.target.value as
                                            '' | 'WEBSITE' | 'AGENT';

                                        form.setData('referral_source', source);

                                        if (source !== 'AGENT') {
                                            form.setData('agent_name', '');
                                        }
                                    }}
                                >
                                    <option value="">Pilih sumber</option>
                                    <option value="WEBSITE">Site</option>
                                    <option value="AGENT">Agent</option>
                                </select>
                            </label>
                            {form.data.referral_source === 'AGENT' ? (
                                <label>
                                    <span className="mb-2 block text-sm font-medium">
                                        Nama agent
                                    </span>
                                    <input
                                        aria-label="Nama agent"
                                        className={inputClass}
                                        value={form.data.agent_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'agent_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                            ) : null}
                            <label className="sm:col-span-2">
                                <span className="mb-2 block text-sm font-medium">
                                    Paket
                                </span>
                                <select
                                    aria-label="Paket"
                                    className={inputClass}
                                    value={form.data.package_code}
                                    onChange={(event) =>
                                        form.setData(
                                            'package_code',
                                            event.target.value as PackageCode,
                                        )
                                    }
                                >
                                    <option value="">Pilih paket</option>
                                    {packages.map((item) => (
                                        <option
                                            key={item.code}
                                            value={item.code}
                                        >
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </section>

                        {needsTable ? (
                            <section className="space-y-4 border-t border-slate-200 pt-6">
                                <label>
                                    <span className="mb-2 block text-sm font-medium">
                                        Nomor meja
                                    </span>
                                    <select
                                        aria-label="Nomor meja"
                                        className={inputClass}
                                        value={form.data.table_slot_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'table_slot_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Pilih meja tersedia
                                        </option>
                                        {table_slots.map((slot) => (
                                            <option
                                                key={slot.id}
                                                value={slot.id}
                                            >
                                                {slot.code}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                {form.data.deceased_names.map((name, index) => (
                                    <div
                                        key={name.position}
                                        className="space-y-3 rounded-2xl bg-slate-50 p-4"
                                    >
                                        <h2 className="font-semibold">
                                            Nama kertas doa {index + 1}
                                            {index === 1 ? ' (opsional)' : ''}
                                        </h2>
                                        <input
                                            aria-label={`Nama Indonesia doa ${index + 1}`}
                                            placeholder="Nama Indonesia"
                                            className={inputClass}
                                            value={name.indonesian_name}
                                            onChange={(event) =>
                                                updatePrayerName(
                                                    index,
                                                    'indonesian_name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <textarea
                                            aria-label={`Nama Mandarin doa ${index + 1}`}
                                            rows={3}
                                            placeholder="Nama Mandarin, boleh beberapa baris"
                                            className={inputClass}
                                            value={name.mandarin_name}
                                            onChange={(event) =>
                                                updatePrayerName(
                                                    index,
                                                    'mandarin_name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <input
                                            ref={prayerFileRefs[index]}
                                            type="file"
                                            accept="image/jpeg,image/png"
                                            className="hidden"
                                            onChange={(event) =>
                                                void readPhoto(
                                                    event.target.files?.[0],
                                                    `prayer-${index}`,
                                                    (text) =>
                                                        updatePrayerName(
                                                            index,
                                                            'mandarin_name',
                                                            text,
                                                        ),
                                                )
                                            }
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                prayerFileRefs[
                                                    index
                                                ].current?.click()
                                            }
                                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                                        >
                                            Baca foto nama doa {index + 1}
                                        </button>
                                        {ocrStatus[`prayer-${index}`] ? (
                                            <p className="text-sm text-slate-700">
                                                {ocrStatus[`prayer-${index}`]}
                                            </p>
                                        ) : null}
                                    </div>
                                ))}
                            </section>
                        ) : null}

                        {needsIncense ? (
                            <section className="space-y-4 border-t border-slate-200 pt-6">
                                <label>
                                    <span className="mb-2 block text-sm font-medium">
                                        Nomor hio
                                    </span>
                                    <select
                                        aria-label="Nomor hio"
                                        className={inputClass}
                                        value={form.data.incense_slot_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'incense_slot_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Pilih hio tersedia
                                        </option>
                                        {incense_slots.map((slot) => (
                                            <option
                                                key={slot.id}
                                                value={slot.id}
                                            >
                                                {slot.number}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <div className="space-y-3 rounded-2xl bg-slate-50 p-4">
                                    <h2 className="font-semibold">
                                        Nama kertas hio
                                    </h2>
                                    <input
                                        aria-label="Nama Indonesia hio"
                                        placeholder="Nama Indonesia"
                                        className={inputClass}
                                        value={
                                            form.data.incense_name
                                                .indonesian_name
                                        }
                                        onChange={(event) =>
                                            form.setData('incense_name', {
                                                ...form.data.incense_name,
                                                indonesian_name:
                                                    event.target.value,
                                            })
                                        }
                                    />
                                    <textarea
                                        aria-label="Nama Mandarin hio"
                                        rows={3}
                                        placeholder="Nama Mandarin, boleh beberapa baris"
                                        className={inputClass}
                                        value={
                                            form.data.incense_name.mandarin_name
                                        }
                                        onChange={(event) =>
                                            form.setData('incense_name', {
                                                ...form.data.incense_name,
                                                mandarin_name:
                                                    event.target.value,
                                            })
                                        }
                                    />
                                    <input
                                        ref={incenseFileRef}
                                        type="file"
                                        accept="image/jpeg,image/png"
                                        className="hidden"
                                        onChange={(event) =>
                                            void readPhoto(
                                                event.target.files?.[0],
                                                'incense',
                                                (text) =>
                                                    form.setData(
                                                        'incense_name',
                                                        {
                                                            ...form.data
                                                                .incense_name,
                                                            mandarin_name: text,
                                                        },
                                                    ),
                                            )
                                        }
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            incenseFileRef.current?.click()
                                        }
                                        className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                                    >
                                        Baca foto nama hio
                                    </button>
                                    {ocrStatus.incense ? (
                                        <p className="text-sm text-slate-700">
                                            {ocrStatus.incense}
                                        </p>
                                    ) : null}
                                </div>
                            </section>
                        ) : null}

                        {Object.keys(form.errors).length > 0 ? (
                            <div className="space-y-1 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                                {Object.values(form.errors).map((error) => (
                                    <p key={error}>{error}</p>
                                ))}
                            </div>
                        ) : null}

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="min-h-12 w-full rounded-full bg-emerald-600 px-5 py-4 text-base font-semibold text-white disabled:opacity-60"
                        >
                            {form.processing
                                ? 'Menyimpan...'
                                : 'Simpan dan langsung approve'}
                        </button>
                    </form>
                </div>
            </main>
        </>
    );
}
