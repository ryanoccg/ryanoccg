// Money helpers. All settlement math runs in integer cents to avoid float drift.

/** Parse a number|string (possibly null) into cents (integer). */
export function toCents(v: number | string | null | undefined): number {
  if (v === null || v === undefined || v === '') return 0
  const n = typeof v === 'number' ? v : parseFloat(v)
  if (!isFinite(n)) return 0
  return Math.round(n * 100)
}

/** Parse a number|string into a float (dollars). */
export function toNum(v: number | string | null | undefined): number {
  if (v === null || v === undefined || v === '') return 0
  const n = typeof v === 'number' ? v : parseFloat(v)
  return isFinite(n) ? n : 0
}

/** Format cents as a localized currency string. */
export function formatCents(cents: number, currency = 'USD'): string {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(cents / 100)
  } catch {
    return (cents / 100).toFixed(2) + ' ' + currency
  }
}
