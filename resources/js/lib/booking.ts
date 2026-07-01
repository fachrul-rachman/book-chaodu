export function formatCurrency(value: string | number | null): string {
    if (value === null || value === '') {
        return 'Belum diatur';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

export function formatNominalInput(value: string): string {
    const digits = onlyDigits(value);

    if (!digits) {
        return '';
    }

    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 0,
    }).format(Number(digits));
}

export function onlyDigits(value: string): string {
    return value.replace(/\D/g, '');
}

export function createIdempotencyKey(): string {
    if (
        typeof window !== 'undefined' &&
        'crypto' in window &&
        'randomUUID' in window.crypto
    ) {
        return window.crypto.randomUUID();
    }

    return `booking-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}
