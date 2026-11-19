/*
 * A view's setup kept for the browser tab's session (sessionStorage), so a list
 * someone filtered, sorted or switched is still set up that way when they come
 * back to it — whether by the Back button, a back link or the sidebar. Pure
 * read/write helpers (unit-tested); the React hook is `useStoredState`.
 */

const PREFIX = 'yolt:view:';

function storage(): Storage | null {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage;
    } catch {
        return null; // storage blocked
    }
}

/** The stored value for a key, or `fallback` when absent or unreadable. */
export function readStored<T>(key: string, fallback: T): T {
    const raw = storage()?.getItem(PREFIX + key);
    if (raw == null) return fallback;
    try {
        return JSON.parse(raw) as T;
    } catch {
        return fallback;
    }
}

export function writeStored<T>(key: string, value: T): void {
    try {
        storage()?.setItem(PREFIX + key, JSON.stringify(value));
    } catch {
        // Full or blocked storage: the view just isn't remembered.
    }
}
