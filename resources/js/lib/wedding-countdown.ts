/** Resolve a canonical calendar date/time in an explicit IANA zone, never the browser's local zone.
 * Nonexistent or ambiguous DST wall times fail closed until a future server contract disambiguates them.
 */
export function weddingInstant(date: string | null, time: string | null, timeZone: string): number | null {
    if (!date || !time || !/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time)) return null;
    if (timeZone !== 'UTC' && !/^[A-Za-z_]+(?:\/[A-Za-z0-9_+-]+)+$/.test(timeZone)) return null;
    const [year, month, day] = date.split('-').map(Number);
    const [hour, minute] = time.split(':').map(Number);
    if (year < 1900 || year > 9999 || hour > 23 || minute > 59) return null;
    const wallTime = Date.UTC(year, month - 1, day, hour, minute);
    if (new Date(wallTime).toISOString().slice(0, 10) !== date) return null;
    try {
        const formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone,
            calendar: 'gregory',
            numberingSystem: 'latn',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        });
        const asWallTime = (instant: number): number => {
            const parts = Object.fromEntries(formatter.formatToParts(instant).map((part) => [part.type, part.value]));
            return Date.UTC(
                Number(parts.year),
                Number(parts.month) - 1,
                Number(parts.day),
                Number(parts.hour),
                Number(parts.minute),
                Number(parts.second),
            );
        };
        const offsets = new Set(
            [-36, 0, 36].map((hours) => {
                const sample = wallTime + hours * 3600000;
                return asWallTime(sample) - sample;
            }),
        );
        const matches = Array.from(offsets, (offset) => wallTime - offset).filter((instant) => asWallTime(instant) === wallTime);
        return matches.length === 1 ? matches[0] : null;
    } catch {
        return null;
    }
}

export function countdownUnits(instant: number, now: number): readonly number[] {
    const seconds = Math.max(0, Math.ceil((instant - now) / 1000));
    return [Math.floor(seconds / 86400), Math.floor(seconds / 3600) % 24, Math.floor(seconds / 60) % 60, seconds % 60];
}
