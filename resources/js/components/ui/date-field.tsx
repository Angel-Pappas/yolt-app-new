import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import { DayPicker } from 'react-day-picker';
import 'react-day-picker/style.css';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

/** Format digits as they are typed into a dd/mm/yyyy mask. */
function mask(raw: string): string {
    const d = raw.replace(/\D/g, '').slice(0, 8);
    return [d.slice(0, 2), d.slice(2, 4), d.slice(4, 8)].filter(Boolean).join('/');
}

function toIso(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * A locale-independent date input that always reads/writes dd/mm/yyyy on screen
 * while emitting an ISO `yyyy-mm-dd` value — so the display never falls back to the
 * browser's locale (e.g. American mm/dd/yyyy). Type the digits (slashes auto-fill)
 * or pick from the calendar.
 */
export function DateField({
    id,
    value,
    onChange,
    required,
    showCalendar = true,
}: Props) {
    const [display, setDisplay] = useState(isoToDisplay(value));
    const [lastValue, setLastValue] = useState(value);
    const [open, setOpen] = useState(false);

    // Re-sync the display when the ISO value changes from outside (e.g. the
    // invoice date following the transaction date).
    if (value !== lastValue) {
        setLastValue(value);
        setDisplay(isoToDisplay(value));
    }

    function handleInput(raw: string) {
        const masked = mask(raw);
        setDisplay(masked);
        if (masked === '') {
            onChange('');
        } else {
            const iso = displayToIso(masked);
            if (iso) onChange(iso);
        }
    }

    function handleBlur() {
        if (display === '') {
            onChange('');
            return;
        }
        const iso = displayToIso(display);
        if (iso) {
            onChange(iso);
        } else {
            setDisplay(isoToDisplay(value)); // revert an incomplete/invalid entry
        }
    }

    const selected = value ? new Date(`${value.slice(0, 10)}T00:00:00`) : undefined;

    return (
        <div className="relative">
            <Input
                id={id}
                inputMode="numeric"
                placeholder="dd/mm/yyyy"
                value={display}
                onChange={(e) => handleInput(e.target.value)}
                onBlur={handleBlur}
                required={required}
                className={cn(showCalendar && 'pr-9')}
            />
            {showCalendar && (
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="absolute top-0 right-0 h-full text-muted-foreground"
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
                                    setDisplay(isoToDisplay(iso));
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
