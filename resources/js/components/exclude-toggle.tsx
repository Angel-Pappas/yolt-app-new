import { router } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * The on/off switch every item that can be left out of the calculations carries — a
 * transaction, a recurrence, a project. On (an open eye) the item counts; off (a
 * struck eye) it stays listed but counts for nothing. `url` is the item's
 * `…/excluded` endpoint.
 */
export function ExcludeToggle({
    url,
    excluded,
    label,
    disabledReason,
    className,
}: {
    url: string;
    excluded: boolean;
    /** What is being switched, for the tooltip: "transaction", "project", … */
    label: string;
    /** Set when the item can't be switched off (e.g. it is reconciled). */
    disabledReason?: string;
    className?: string;
}) {
    const title =
        disabledReason ??
        (excluded
            ? `Excluded from the calculations — include this ${label}`
            : `Exclude this ${label} from the calculations`);

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            title={title}
            aria-label={title}
            aria-pressed={excluded}
            disabled={disabledReason !== undefined}
            className={cn(excluded && 'text-amber-600', className)}
            onClick={(e) => {
                e.stopPropagation();
                router.patch(
                    url,
                    { excluded: !excluded },
                    { preserveScroll: true, preserveState: true },
                );
            }}
        >
            {excluded ? (
                <EyeOff className="size-4" />
            ) : (
                <Eye className="size-4" />
            )}
        </Button>
    );
}
