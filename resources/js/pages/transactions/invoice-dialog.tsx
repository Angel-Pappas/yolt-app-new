import { useForm } from '@inertiajs/react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transactionId: number;
    /** Current 1–12 filed month, or 13 for "not required", or '' for unreviewed. */
    current: string;
};

export function InvoiceDialog({
    open,
    onOpenChange,
    transactionId,
    current,
}: Props) {
    const form = useForm({ month: current });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({ month: data.month || null }));
        form.post(`/transactions/${transactionId}/invoice`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-sm">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Invoice folder</DialogTitle>
                        <DialogDescription>
                            The month folder this transaction's invoice was
                            filed under (1–12). Enter 13 for &ldquo;no invoice
                            needed&rdquo;, or clear it to leave unreviewed.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2 py-4">
                        <Label htmlFor="month">Month (1–13)</Label>
                        <Input
                            id="month"
                            inputMode="numeric"
                            value={form.data.month}
                            onChange={(e) =>
                                form.setData(
                                    'month',
                                    e.target.value.replace(/\D/g, ''),
                                )
                            }
                            autoFocus
                        />
                        <InputError message={form.errors.month} />
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
