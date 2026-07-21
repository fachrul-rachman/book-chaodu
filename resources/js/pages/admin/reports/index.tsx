import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { formatCurrency } from '@/lib/booking';

type Filters = {
    tab: 'checkin' | 'finance' | 'agent' | 'customer';
    date_field: 'booking' | 'approval';
    date_from: string | null;
    date_to: string | null;
    package_code: string | null;
    sort:
        'table_number' | 'incense_number' | 'customer_name' | 'booking_number';
    agent_search: string | null;
    page: number;
};

type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    filters: Filters;
    tabs: Array<{ value: Filters['tab']; label: string }>;
    sort_options: Array<{ value: Filters['sort']; label: string }>;
    package_options: Array<{ value: string; label: string }>;
    checkin: {
        rows: Array<{
            booking_number: string;
            customer_name: string;
            customer_phone: string;
            package_name: string;
            attendee_count: number;
            vegetarian_quantity: number;
            non_vegetarian_quantity: number;
            table_number: string;
            incense_number: string;
            agent_name: string | null;
        }>;
        filter_lines: string[];
        pagination: PaginationMeta;
    };
    finance: {
        summary: {
            total_bookings: number;
            total_revenue: number;
            by_package: Array<{
                package_code: string;
                package_name: string;
                booking_count: number;
                total_revenue: number;
            }>;
        };
        rows: Array<{
            booking_number: string;
            booking_date: string | null;
            approval_date: string | null;
            customer_name: string;
            package_name: string;
            amount: number;
            virtual_account_number: string | null;
            referral_source: string;
            agent_name: string | null;
        }>;
        filter_lines: string[];
        pagination: PaginationMeta;
    };
    agent: {
        groups: Array<{
            key: string;
            display_name: string;
            booking_count: number;
            attendee_count: number;
            total_value: number;
            bookings: Array<{
                booking_number: string;
                booking_date: string | null;
                approval_date: string | null;
                customer_name: string;
                package_name: string;
                attendee_count: number;
                amount: number;
            }>;
        }>;
        filter_lines: string[];
        pagination: PaginationMeta;
    };
    customer: {
        summary: {
            total_bookings: number;
            by_package: Array<{
                package_code: string;
                package_name: string;
                booking_count: number;
            }>;
        };
        rows: Array<{
            booking_number: string;
            booking_date: string | null;
            status: string;
            customer_name: string;
            customer_phone: string;
            customer_email: string;
            package_code: string;
            package_name: string;
            prayer_paper_1: CustomerPaper;
            prayer_paper_2: CustomerPaper;
            incense_paper: CustomerPaper;
        }>;
        filter_lines: string[];
        pagination: PaginationMeta;
    };
    export_urls: Record<string, { xlsx: string; pdf: string }>;
};

type CustomerPaper = {
    name: string | null;
    image_url: string | null;
};

function ReportPagination({
    pagination,
    onPageChange,
}: {
    pagination: PaginationMeta;
    onPageChange: (page: number) => void;
}) {
    if (pagination.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Halaman laporan"
            className="mt-6 flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between print:hidden"
        >
            <p className="text-sm text-slate-600">
                Menampilkan {pagination.from ?? 0}-{pagination.to ?? 0} dari{' '}
                {pagination.total} data
            </p>

            <div className="flex items-center gap-3">
                <button
                    type="button"
                    disabled={pagination.current_page <= 1}
                    onClick={() => onPageChange(pagination.current_page - 1)}
                    className="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Sebelumnya
                </button>
                <span className="text-sm font-medium text-slate-700">
                    Halaman {pagination.current_page} dari{' '}
                    {pagination.last_page}
                </span>
                <button
                    type="button"
                    disabled={pagination.current_page >= pagination.last_page}
                    onClick={() => onPageChange(pagination.current_page + 1)}
                    className="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Berikutnya
                </button>
            </div>
        </nav>
    );
}

function buildQuery(filters: Filters): string {
    const params = new URLSearchParams();

    params.set('tab', filters.tab);
    params.set('date_field', filters.date_field);

    if (filters.date_from) {
        params.set('date_from', filters.date_from);
    }

    if (filters.date_to) {
        params.set('date_to', filters.date_to);
    }

    if (filters.package_code) {
        params.set('package_code', filters.package_code);
    }

    if (filters.sort) {
        params.set('sort', filters.sort);
    }

    if (filters.agent_search) {
        params.set('agent_search', filters.agent_search);
    }

    return params.toString();
}

export default function AdminReportsPage() {
    const {
        filters,
        tabs,
        sort_options,
        package_options,
        checkin,
        finance,
        agent,
        customer,
        export_urls,
    } = usePage<Props>().props;
    const [form, setForm] = useState({
        ...filters,
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        package_code: filters.package_code ?? '',
        agent_search: filters.agent_search ?? '',
    });

    const activeExportUrls = export_urls[filters.tab];
    const exportQuery = useMemo(
        () =>
            buildQuery({
                ...filters,
                tab: filters.tab,
            }),
        [filters],
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            '/admin/laporan',
            {
                ...form,
                page: 1,
                date_from: form.date_from || undefined,
                date_to: form.date_to || undefined,
                package_code: form.package_code || undefined,
                agent_search: form.agent_search || undefined,
            },
            { preserveScroll: true },
        );
    };

    const changeTab = (tab: Filters['tab']) => {
        router.get(
            '/admin/laporan',
            {
                ...filters,
                tab,
                page: 1,
            },
            { preserveScroll: true },
        );
    };

    const changePage = (page: number) => {
        router.get(
            '/admin/laporan',
            {
                ...filters,
                page,
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Laporan" />

            <style>{`
                @media print {
                    @page {
                        size: landscape;
                        margin: 10mm;
                    }

                    html, body {
                        background: #ffffff !important;
                    }

                    main {
                        min-height: auto !important;
                        padding: 0 !important;
                        background: #ffffff !important;
                    }

                    .print-checkin-wrap {
                        max-width: none !important;
                        width: 100% !important;
                    }

                    .print-checkin-card {
                        border: 0 !important;
                        box-shadow: none !important;
                        border-radius: 0 !important;
                        padding: 0 !important;
                    }

                    .print-checkin-table-wrap {
                        overflow: visible !important;
                    }

                    .print-checkin-table {
                        width: 100% !important;
                        min-width: 0 !important;
                        table-layout: fixed !important;
                        font-size: 10px !important;
                    }

                    .print-checkin-table th,
                    .print-checkin-table td {
                        padding: 4px !important;
                        word-break: break-word;
                    }
                }
            `}</style>

            <main className="min-h-screen bg-[var(--color-bg,#f8fafc)] px-4 py-8 sm:px-6">
                <div className="print-checkin-wrap mx-auto max-w-7xl space-y-6">
                    <div className="flex items-center justify-between gap-4 print:hidden">
                        <div>
                            <h1 className="text-3xl font-semibold text-slate-900">
                                Laporan
                            </h1>
                            <p className="mt-2 text-sm text-slate-600">
                                Semua data di halaman ini hanya memakai booking
                                yang sudah disetujui.
                            </p>
                        </div>

                        <Link
                            href="/admin"
                            className="rounded-full border border-[var(--color-brand)] px-4 py-2 text-sm font-semibold text-[var(--color-brand)]"
                        >
                            Kembali
                        </Link>
                    </div>

                    <section className="rounded-[24px] border border-[var(--color-border)] bg-white/90 p-4 shadow-sm sm:p-6 print:hidden">
                        <div className="flex flex-wrap gap-3">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.value}
                                    type="button"
                                    onClick={() => changeTab(tab.value)}
                                    className={`rounded-full px-4 py-2 text-sm font-semibold transition ${
                                        filters.tab === tab.value
                                            ? 'bg-[var(--color-brand)] text-white'
                                            : 'border border-slate-200 bg-white text-slate-700'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        <form
                            onSubmit={submit}
                            className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
                        >
                            <label className="space-y-2 text-sm text-slate-700">
                                <span>Pakai tanggal</span>
                                <select
                                    value={form.date_field}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            date_field: event.target
                                                .value as Filters['date_field'],
                                        }))
                                    }
                                    className="w-full rounded-2xl border border-slate-200 px-4 py-3"
                                >
                                    <option value="booking">
                                        Tanggal booking
                                    </option>
                                    <option value="approval">
                                        Tanggal setuju
                                    </option>
                                </select>
                            </label>

                            <label className="space-y-2 text-sm text-slate-700">
                                <span>Dari tanggal</span>
                                <input
                                    type="date"
                                    value={form.date_from}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            date_from: event.target.value,
                                        }))
                                    }
                                    className="w-full rounded-2xl border border-slate-200 px-4 py-3"
                                />
                            </label>

                            <label className="space-y-2 text-sm text-slate-700">
                                <span>Sampai tanggal</span>
                                <input
                                    type="date"
                                    value={form.date_to}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            date_to: event.target.value,
                                        }))
                                    }
                                    className="w-full rounded-2xl border border-slate-200 px-4 py-3"
                                />
                            </label>

                            <label className="space-y-2 text-sm text-slate-700">
                                <span>Paket</span>
                                <select
                                    value={form.package_code}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            package_code: event.target.value,
                                        }))
                                    }
                                    className="w-full rounded-2xl border border-slate-200 px-4 py-3"
                                >
                                    <option value="">Semua paket</option>
                                    {package_options.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            {filters.tab === 'agent' ? (
                                <label className="space-y-2 text-sm text-slate-700">
                                    <span>Cari agent</span>
                                    <input
                                        type="text"
                                        value={form.agent_search}
                                        onChange={(event) =>
                                            setForm((current) => ({
                                                ...current,
                                                agent_search:
                                                    event.target.value,
                                            }))
                                        }
                                        placeholder="Nama agent"
                                        className="w-full rounded-2xl border border-slate-200 px-4 py-3"
                                    />
                                </label>
                            ) : (
                                <label className="space-y-2 text-sm text-slate-700">
                                    <span>Urutkan</span>
                                    <select
                                        value={form.sort}
                                        onChange={(event) =>
                                            setForm((current) => ({
                                                ...current,
                                                sort: event.target
                                                    .value as Filters['sort'],
                                            }))
                                        }
                                        className="w-full rounded-2xl border border-slate-200 px-4 py-3"
                                    >
                                        {sort_options.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}

                            <div className="flex flex-wrap gap-3 xl:col-span-5">
                                <button
                                    type="submit"
                                    className="rounded-full bg-[var(--color-brand)] px-5 py-3 text-sm font-semibold text-white"
                                >
                                    Terapkan
                                </button>

                                {activeExportUrls ? (
                                    <>
                                        <a
                                            href={`${activeExportUrls.xlsx}?${exportQuery}`}
                                            className="rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700"
                                        >
                                            Export Excel
                                        </a>

                                        <a
                                            href={`${activeExportUrls.pdf}?${exportQuery}`}
                                            className="rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700"
                                        >
                                            Export PDF
                                        </a>
                                    </>
                                ) : null}

                                {filters.tab === 'checkin' ? (
                                    <button
                                        type="button"
                                        onClick={() => window.print()}
                                        className="rounded-full border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700"
                                    >
                                        Cetak
                                    </button>
                                ) : null}
                            </div>
                        </form>
                    </section>

                    {filters.tab === 'checkin' ? (
                        <section className="print-checkin-card rounded-[24px] border border-[var(--color-border)] bg-white p-4 shadow-sm sm:p-6">
                            <div className="mb-4">
                                <h2 className="text-2xl font-semibold text-slate-900">
                                    Laporan Check-in
                                </h2>
                                <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                                    {checkin.filter_lines.map((line) => (
                                        <span
                                            key={line}
                                            className="rounded-full bg-slate-100 px-3 py-1"
                                        >
                                            {line}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="print-checkin-table-wrap overflow-x-auto">
                                <table className="print-checkin-table min-w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left text-slate-600">
                                            {[
                                                'Nomor booking',
                                                'Nama customer',
                                                'Nomor telepon',
                                                'Paket',
                                                'Hadir',
                                                'Veg',
                                                'Non veg',
                                                'Meja',
                                                'Hio',
                                                'Agent',
                                                'Check-in manual',
                                                'Catatan',
                                            ].map((label) => (
                                                <th
                                                    key={label}
                                                    className="px-3 py-3 font-semibold"
                                                >
                                                    {label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {checkin.rows.map((row) => (
                                            <tr
                                                key={row.booking_number}
                                                className="border-b border-slate-100 align-top"
                                            >
                                                <td className="px-3 py-3">
                                                    {row.booking_number}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.customer_name}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.customer_phone}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.package_name}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.attendee_count}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.vegetarian_quantity}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {
                                                        row.non_vegetarian_quantity
                                                    }
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.table_number || '-'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.incense_number || '-'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.agent_name || '-'}
                                                </td>
                                                <td className="h-12 min-w-32 px-3 py-3"></td>
                                                <td className="h-12 min-w-40 px-3 py-3"></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <ReportPagination
                                pagination={checkin.pagination}
                                onPageChange={changePage}
                            />
                        </section>
                    ) : null}

                    {filters.tab === 'finance' ? (
                        <section className="rounded-[24px] border border-[var(--color-border)] bg-white p-4 shadow-sm sm:p-6">
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <div className="rounded-2xl bg-slate-50 p-4">
                                    <p className="text-sm text-slate-500">
                                        Total booking approved
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold text-slate-900">
                                        {finance.summary.total_bookings}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-slate-50 p-4 md:col-span-1 xl:col-span-3">
                                    <p className="text-sm text-slate-500">
                                        Total uang masuk
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold text-slate-900">
                                        {formatCurrency(
                                            finance.summary.total_revenue,
                                        )}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                                {finance.summary.by_package.map((item) => (
                                    <div
                                        key={item.package_code}
                                        className="rounded-2xl border border-slate-200 p-4"
                                    >
                                        <p className="text-sm text-slate-500">
                                            {item.package_name}
                                        </p>
                                        <p className="mt-2 text-lg font-semibold text-slate-900">
                                            {item.booking_count} booking
                                        </p>
                                        <p className="mt-1 text-sm text-slate-600">
                                            {formatCurrency(item.total_revenue)}
                                        </p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-6 flex flex-wrap gap-2 text-xs text-slate-600">
                                {finance.filter_lines.map((line) => (
                                    <span
                                        key={line}
                                        className="rounded-full bg-slate-100 px-3 py-1"
                                    >
                                        {line}
                                    </span>
                                ))}
                            </div>

                            <div className="mt-6 overflow-x-auto">
                                <table className="min-w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 text-left text-slate-600">
                                            {[
                                                'Nomor booking',
                                                'Tanggal booking',
                                                'Tanggal setuju',
                                                'Nama customer',
                                                'Paket',
                                                'Nominal',
                                                'Nomor VA',
                                                'Sumber',
                                                'Agent',
                                            ].map((label) => (
                                                <th
                                                    key={label}
                                                    className="px-3 py-3 font-semibold"
                                                >
                                                    {label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {finance.rows.map((row) => (
                                            <tr
                                                key={row.booking_number}
                                                className="border-b border-slate-100"
                                            >
                                                <td className="px-3 py-3">
                                                    {row.booking_number}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.booking_date || '-'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.approval_date || '-'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.customer_name}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.package_name}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {formatCurrency(row.amount)}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.virtual_account_number ||
                                                        '-'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.referral_source}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {row.agent_name || '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <ReportPagination
                                pagination={finance.pagination}
                                onPageChange={changePage}
                            />
                        </section>
                    ) : null}

                    {filters.tab === 'agent' ? (
                        <section className="rounded-[24px] border border-[var(--color-border)] bg-white p-4 shadow-sm sm:p-6">
                            <div className="mb-6 flex flex-wrap gap-2 text-xs text-slate-600">
                                {agent.filter_lines.map((line) => (
                                    <span
                                        key={line}
                                        className="rounded-full bg-slate-100 px-3 py-1"
                                    >
                                        {line}
                                    </span>
                                ))}
                            </div>

                            <div className="space-y-4">
                                {agent.groups.map((group) => (
                                    <div
                                        key={group.key}
                                        className="rounded-2xl border border-slate-200 p-4"
                                    >
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <h2 className="text-xl font-semibold text-slate-900">
                                                    {group.display_name}
                                                </h2>
                                                <p className="mt-1 text-sm text-slate-600">
                                                    {group.booking_count}{' '}
                                                    booking |{' '}
                                                    {group.attendee_count} orang
                                                    |{' '}
                                                    {formatCurrency(
                                                        group.total_value,
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="mt-4 overflow-x-auto">
                                            <table className="min-w-full border-collapse text-sm">
                                                <thead>
                                                    <tr className="border-b border-slate-200 text-left text-slate-600">
                                                        {[
                                                            'Nomor booking',
                                                            'Tanggal booking',
                                                            'Tanggal setuju',
                                                            'Nama customer',
                                                            'Paket',
                                                            'Jumlah hadir',
                                                            'Nominal',
                                                        ].map((label) => (
                                                            <th
                                                                key={label}
                                                                className="px-3 py-3 font-semibold"
                                                            >
                                                                {label}
                                                            </th>
                                                        ))}
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {group.bookings.map(
                                                        (row) => (
                                                            <tr
                                                                key={
                                                                    row.booking_number
                                                                }
                                                                className="border-b border-slate-100"
                                                            >
                                                                <td className="px-3 py-3">
                                                                    {
                                                                        row.booking_number
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-3">
                                                                    {row.booking_date ||
                                                                        '-'}
                                                                </td>
                                                                <td className="px-3 py-3">
                                                                    {row.approval_date ||
                                                                        '-'}
                                                                </td>
                                                                <td className="px-3 py-3">
                                                                    {
                                                                        row.customer_name
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-3">
                                                                    {
                                                                        row.package_name
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-3">
                                                                    {
                                                                        row.attendee_count
                                                                    }
                                                                </td>
                                                                <td className="px-3 py-3">
                                                                    {formatCurrency(
                                                                        row.amount,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <ReportPagination
                                pagination={agent.pagination}
                                onPageChange={changePage}
                            />
                        </section>
                    ) : null}

                    {filters.tab === 'customer' ? (
                        <section className="rounded-[24px] border border-[var(--color-border)] bg-white p-4 shadow-sm sm:p-6">
                            <div className="mb-6 flex flex-wrap gap-2 text-xs text-slate-600">
                                {customer.filter_lines.map((line) => (
                                    <span
                                        key={line}
                                        className="rounded-full bg-slate-100 px-3 py-1"
                                    >
                                        {line}
                                    </span>
                                ))}
                            </div>

                            <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div className="rounded-2xl bg-[var(--color-brand)] p-4 text-white">
                                    <p className="text-sm font-medium text-white/80">
                                        Total booking
                                    </p>
                                    <p className="mt-2 text-3xl font-semibold">
                                        {customer.summary.total_bookings.toLocaleString(
                                            'id-ID',
                                        )}
                                    </p>
                                </div>
                                {customer.summary.by_package.map((item) => (
                                    <div
                                        key={item.package_code}
                                        className="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                                    >
                                        <p className="text-sm font-medium text-slate-600">
                                            Paket {item.package_name}
                                        </p>
                                        <p className="mt-2 text-3xl font-semibold text-slate-900">
                                            {item.booking_count.toLocaleString(
                                                'id-ID',
                                            )}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            booking
                                        </p>
                                    </div>
                                ))}
                            </div>

                            {customer.rows.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full border-collapse text-sm">
                                        <thead>
                                            <tr className="border-b border-slate-200 text-left text-slate-600">
                                                {[
                                                    'Nomor booking',
                                                    'Tanggal booking',
                                                    'Status',
                                                    'Nama customer',
                                                    'Nomor telepon',
                                                    'Email',
                                                    'Paket',
                                                    'Kertas Doa 1',
                                                    'Kertas Doa 2',
                                                    'Kertas Hio',
                                                ].map((label) => (
                                                    <th
                                                        key={label}
                                                        className="px-3 py-3 font-semibold"
                                                    >
                                                        {label}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {customer.rows.map((row) => (
                                                <tr
                                                    key={row.booking_number}
                                                    className="border-b border-slate-100 align-top"
                                                >
                                                    <td className="px-3 py-3 font-medium text-slate-900">
                                                        {row.booking_number}
                                                    </td>
                                                    <td className="px-3 py-3 whitespace-nowrap">
                                                        {row.booking_date ??
                                                            '-'}
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        <span className="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                                            {row.status ===
                                                            'APPROVED'
                                                                ? 'Disetujui'
                                                                : row.status}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        {row.customer_name}
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        {row.customer_phone}
                                                    </td>
                                                    <td className="max-w-64 px-3 py-3 break-words">
                                                        {row.customer_email}
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        {row.package_name}
                                                    </td>
                                                    {[
                                                        {
                                                            key: 'prayer-1',
                                                            paper: row.prayer_paper_1,
                                                        },
                                                        {
                                                            key: 'prayer-2',
                                                            paper: row.prayer_paper_2,
                                                        },
                                                        {
                                                            key: 'incense',
                                                            paper: row.incense_paper,
                                                        },
                                                    ].map(({ key, paper }) => (
                                                        <td
                                                            key={key}
                                                            className="min-w-40 px-3 py-3"
                                                        >
                                                            <p className="font-medium whitespace-pre-line text-slate-900">
                                                                {paper.name ??
                                                                    '-'}
                                                            </p>
                                                            {paper.image_url ? (
                                                                <a
                                                                    href={
                                                                        paper.image_url
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="mt-2 inline-flex min-h-10 items-center rounded-lg border border-[var(--color-brand)] px-3 py-2 font-semibold text-[var(--color-brand)]"
                                                                >
                                                                    Lihat gambar
                                                                </a>
                                                            ) : (
                                                                <p className="mt-2 text-xs text-slate-500">
                                                                    Gambar belum
                                                                    tersedia
                                                                </p>
                                                            )}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <p className="py-10 text-center text-sm text-slate-600">
                                    Belum ada data customer yang sesuai.
                                </p>
                            )}
                            <ReportPagination
                                pagination={customer.pagination}
                                onPageChange={changePage}
                            />
                        </section>
                    ) : null}
                </div>
            </main>
        </>
    );
}
