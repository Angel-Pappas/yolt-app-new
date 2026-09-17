import { useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { type Transaction } from './types';

/**
 * The ⓘ button in a transaction row: hovering previews the description (clamped with
 * "…" when long); clicking opens the full editable description modal. It replaced the
 * Description column and the edit pencil.
 */
export function DescriptionButton({
    description,
    onClick,
}: {
    description: string;
    onClick: () => void;
}) {
    const text = description.trim();
    return (
        <TooltipProvider delayDuration={200}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={onClick}
                        aria-label="Description"
                        title="Description"
                    >
                        <Info className="size-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent className="max-w-xs">
                    <p className="line-clamp-6 whitespace-pre-wrap">
                        {text || 'No description'}
                    </p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

/**
 * A focused modal that shows and edits **only** a transaction's description (not the
 * whole transaction) — the ⓘ click target. Seeded from the transaction, so remount it
 * via `key` on each open. Saves through the dedicated description endpoint and reloads
 * just the current list.
 */
export function DescriptionDialog({
    transaction,
    open,
    onOpenChange,
}: {
    transaction: Transaction | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ description: transaction?.description ?? '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        if (!transaction) return;
        form.patch(`/transactions/${transaction.id}/description`, {
            preserveState: true,
            preserveScroll: true,
            only: ['transactions'],
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Description</DialogTitle>
                        <DialogDescription>
                            This transaction's description.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-4">
                        <Textarea
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            rows={5}
                            autoFocus
                            placeholder="Add a description…"
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
