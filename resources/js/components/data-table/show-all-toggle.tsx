import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * "Show all": lists that show only their open items by default (One-offs,
 * Recurring) bring back the finished ones — styled with `DONE_ROW_CLASS` — while
 * it's on. Sits at the right end of a list's flex controls row, like the
 * Transactions quick toggles.
 */
export function ShowAllToggle({
    on,
    onChange,
}: {
    on: boolean;
    onChange: (on: boolean) => void;
}) {
    return (
        <Button
            variant="outline"
            size="sm"
            aria-pressed={on}
            className={cn('ml-auto', on && 'text-primary border-primary')}
            onClick={() => onChange(!on)}
        >
            Show all
        </Button>
    );
}
