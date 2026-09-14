import { CalendarIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import { DayPicker } from 'react-day-picker';
import 'react-day-picker/style.css';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { displayToIso, isoToDisplay } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    value: string;
    onChange: (iso: string) => void;
    required?: boolean;
    /** Show the calendar-picker button (kept off for keyboard-only entry). */
    showCalendar?: boolean;
};

type Seg = 'd' | 'm' | 'y';
const SEGS: Seg[] = ['d', 'm', 'y'];
type Parts = Record<Seg, string>;

const EMPTY: Parts = { d: '', m: '', y: '' };

/** Split an ISO `yyyy-mm-dd` into display day/month/year segments. */
function split(iso: string): Parts {
    const disp = isoToDisplay(iso);
    if (!disp) return { ...EMPTY };
    const [d, m, y] = disp.split('/');
    return { d, m, y };
}

function toIso(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function clamp(n: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, n));
}

/**
 * A locale-independent, segmented date input that always reads/writes dd/mm/yyyy
 * on screen while emitting an ISO `yyyy-mm-dd` value — so the display never falls
 * back to the browser's locale (e.g. American mm/dd/yyyy).
 *
 * Three numeric segments (day/month/year): each selects on focus, auto-advances
 * as it fills (a day ≥ 4 or month ≥ 2 first digit advances immediately, since it
 * can't start a two-digit value), clamps to its range, and Arrow up/down nudges
 * it. Backspace on an empty segment steps back; `/` and arrows move between
 * segments; a 1–2 digit year is read as 20xx and an incomplete/invalid date snaps
 * back to the last valid value on blur.
 */
export function DateField({
    id,
    value,
    onChange,
    required,
    showCalendar = true,
}: Props) {
    const [parts, setParts] = useState<Parts>(() => split(value));
    const [lastValue, setLastValue] = useState(value);
    const [open, setOpen] = useState(false);
    const refs = {
        d: useRef<HTMLInputElement>(null),
        m: useRef<HTMLInputElement>(null),
        y: useRef<HTMLInputElement>(null),
    };

    // Re-sync when the ISO value changes from outside (e.g. the invoice date
    // following the transaction date, or a filter being cleared).
    if (value !== lastValue) {
        setLastValue(value);
        setParts(split(value));
    }

    function focusSeg(seg: Seg) {
        const el = refs[seg].current;
        if (el) {
            el.focus();
            el.select();
        }
    }

    /** Set the segments and emit an ISO value only when it's complete + valid. */
    function apply(next: Parts) {
        setParts(next);
        if (!next.d && !next.m && !next.y) {
            if (value) onChange('');
            return;
        }
        if (next.d && next.m && next.y.length === 4) {
            const iso = displayToIso(`${next.d}/${next.m}/${next.y}`);
            if (iso) onChange(iso);
        }
    }

    function handleChange(seg: Seg, raw: string) {
        const maxLen = seg === 'y' ? 4 : 2;
        let v = raw.replace(/\D/g, '').slice(0, maxLen);
        if (v.length === 2) {
            const n = Number(v);
            if (seg === 'd' && n > 31) v = '31';
            if (seg === 'm' && n > 12) v = '12';
        }
        apply({ ...parts, [seg]: v });

        const idx = SEGS.indexOf(seg);
        const full = seg === 'y' ? v.length === 4 : v.length === 2;
        const decisiveFirstDigit =
            (seg === 'd' && v.length === 1 && Number(v) > 3) ||
            (seg === 'm' && v.length === 1 && Number(v) > 1);
        if ((full || decisiveFirstDigit) && idx < SEGS.length - 1) {
            focusSeg(SEGS[idx + 1]);
        }
    }

    function nudge(seg: Seg, dir: 1 | -1) {
        if (seg === 'y') {
            const base = parts.y ? Number(parts.y) : new Date().getFullYear();
            apply({ ...parts, y: String(clamp(base + dir, 1, 9999)) });
            return;
        }
        const max = seg === 'd' ? 31 : 12;
        const cur = parts[seg] ? Number(parts[seg]) : dir > 0 ? 0 : max + 1;
        let n = cur + dir;
        if (n < 1) n = max;
        if (n > max) n = 1;
        apply({ ...parts, [seg]: String(n).padStart(2, '0') });
    }

    function handleKeyDown(seg: Seg, e: React.KeyboardEvent<HTMLInputElement>) {
        const idx = SEGS.indexOf(seg);
        const el = e.currentTarget;
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            nudge(seg, 1);
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            nudge(seg, -1);
        } else if (e.key === 'Backspace' && el.value === '' && idx > 0) {
            e.preventDefault();
            focusSeg(SEGS[idx - 1]);
        } else if (
            (e.key === '/' || e.key === 'ArrowRight') &&
            el.selectionStart === el.value.length &&
            idx < SEGS.length - 1
        ) {
            e.preventDefault();
            focusSeg(SEGS[idx + 1]);
        } else if (e.key === 'ArrowLeft' && el.selectionStart === 0 && idx > 0) {
            e.preventDefault();
            focusSeg(SEGS[idx - 1]);
        }
    }

    /** Finalize when focus leaves the whole field: expand the year, pad, and
     *  either emit a normalized valid date or snap back to the last valid one. */
    function handleBlur(e: React.FocusEvent<HTMLDivElement>) {
        if (e.currentTarget.contains(e.relatedTarget as Node | null)) return;
        if (!parts.d && !parts.m && !parts.y) {
            if (value) onChange('');
            return;
        }
        let { y } = parts;
        if (y.length > 0 && y.length <= 2) y = String(2000 + Number(y));
        const iso =
            parts.d && parts.m && y.length === 4
                ? displayToIso(`${parts.d.padStart(2, '0')}/${parts.m.padStart(2, '0')}/${y}`)
                : '';
        if (iso) {
            setParts(split(iso));
            onChange(iso);
        } else {
            setParts(split(value)); // revert an incomplete/invalid entry
        }
    }

    function handleMouseDown(e: React.MouseEvent<HTMLDivElement>) {
        const t = e.target as HTMLElement;
        if (t.tagName !== 'INPUT' && !t.closest('button')) {
            e.preventDefault();
            const firstEmpty = SEGS.find((s) => !parts[s]) ?? 'd';
            focusSeg(firstEmpty);
        }
    }

    const selected = value ? new Date(`${value.slice(0, 10)}T00:00:00`) : undefined;

    const segClass =
        'bg-transparent text-center tabular-nums outline-none placeholder:text-muted-foreground';
    const sep = <span className="text-muted-foreground select-none">/</span>;

    return (
        <div
            onBlur={handleBlur}
            onMouseDown={handleMouseDown}
            className={cn(
                'border-input flex h-9 w-full min-w-0 items-center gap-0.5 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] md:text-sm',
                'focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px]',
            )}
        >
            <input
                ref={refs.d}
                id={id}
                inputMode="numeric"
                autoComplete="off"
                placeholder="dd"
                aria-label="Day"
                required={required}
                value={parts.d}
                onFocus={(e) => e.target.select()}
                onChange={(e) => handleChange('d', e.target.value)}
                onKeyDown={(e) => handleKeyDown('d', e)}
                className={cn(segClass, 'w-6')}
            />
            {sep}
            <input
                ref={refs.m}
                inputMode="numeric"
                autoComplete="off"
                placeholder="mm"
                aria-label="Month"
                value={parts.m}
                onFocus={(e) => e.target.select()}
                onChange={(e) => handleChange('m', e.target.value)}
                onKeyDown={(e) => handleKeyDown('m', e)}
                className={cn(segClass, 'w-6')}
            />
            {sep}
            <input
                ref={refs.y}
                inputMode="numeric"
                autoComplete="off"
                placeholder="yyyy"
                aria-label="Year"
                value={parts.y}
                onFocus={(e) => e.target.select()}
                onChange={(e) => handleChange('y', e.target.value)}
                onKeyDown={(e) => handleKeyDown('y', e)}
                className={cn(segClass, 'w-11')}
            />
            {showCalendar && (
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground ml-auto size-7 shrink-0"
                            aria-label="Open calendar"
                        >
                            <CalendarIcon className="size-4" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-2" align="end">
                        <DayPicker
                            mode="single"
                            selected={selected}
                            defaultMonth={selected}
                            onSelect={(d) => {
                                if (d) {
                                    const iso = toIso(d);
                                    onChange(iso);
                                    setParts(split(iso));
                                }
                                setOpen(false);
                            }}
                        />
                    </PopoverContent>
                </Popover>
            )}
        </div>
    );
}
