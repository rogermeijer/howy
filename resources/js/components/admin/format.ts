/**
 * Estimated cost is stored in micro-dollars. Small amounts keep four decimals,
 * so a fraction of a cent still reads as something rather than $0.00.
 */
export function formatCost(micros: number): string {
    const dollars = micros / 1_000_000;

    return `$${dollars.toLocaleString('en-US', {
        minimumFractionDigits: dollars >= 100 ? 2 : 4,
        maximumFractionDigits: dollars >= 100 ? 2 : 4,
    })}`;
}

export function formatNumber(value: number): string {
    return value.toLocaleString('en-US');
}

/** 1234567 → 1.2M, for dense columns. */
export function formatCompact(value: number): string {
    return Intl.NumberFormat('en-US', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

export function formatLatency(ms: number | null): string {
    if (ms === null) {
        return '—';
    }

    return ms >= 1000 ? `${(ms / 1000).toFixed(1)} s` : `${ms} ms`;
}
