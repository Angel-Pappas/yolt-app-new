/** Lower-case, strip accents/tones, and fold Greek final sigma for search matching. */
export function normalizeSearch(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .replace(/ς/g, 'σ');
}

/** Whether `haystack` contains `needle` as a run, ignoring case, accents and tones. */
export function includesNormalized(haystack: string, needle: string): boolean {
    return normalizeSearch(haystack).includes(normalizeSearch(needle));
}
