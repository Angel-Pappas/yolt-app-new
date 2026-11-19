import {
    type Dispatch,
    type SetStateAction,
    useCallback,
    useState,
} from 'react';
import { readStored, writeStored } from '@/lib/stored-state';

/**
 * `useState` whose value is kept for the browser tab's session under `key`, so a
 * choice on a page (a group, "Show all", a filter) is still there when the user
 * comes back to it — by Back, a back link or the sidebar. Keys must be unique
 * across the app (name them after the page).
 */
export function useStoredState<T>(
    key: string,
    initial: T,
    /** When given, wins over what is stored (e.g. a deep link's own setup). */
    override?: T,
): [T, Dispatch<SetStateAction<T>>] {
    const [value, setValue] = useState<T>(() => {
        if (override !== undefined) {
            writeStored(key, override);
            return override;
        }
        return readStored(key, initial);
    });

    const set = useCallback<Dispatch<SetStateAction<T>>>(
        (next) =>
            setValue((current) => {
                const resolved =
                    typeof next === 'function'
                        ? (next as (c: T) => T)(current)
                        : next;
                writeStored(key, resolved);
                return resolved;
            }),
        [key],
    );

    return [value, set];
}
