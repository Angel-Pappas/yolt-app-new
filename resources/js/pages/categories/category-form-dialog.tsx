import { type Page } from '@inertiajs/core';
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type CategoryType = 'income' | 'expense' | 'both';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The type pre-selected when the dialog opens (still changeable). */
    initialType?: CategoryType;
    /** Called with the fresh page after a successful save (e.g. to select the new row). */
    onSaved?: (page: Page) => void;
};

/**
 * The shared "Add category" dialog — used by the Categories page and the transaction
 * form's "+ Add category" button (one source of truth). It offers Income / Expense /
 * **Both**; "Both" creates a matching income *and* expense category in one go (two
 * independent rows). It seeds its type once from `initialType`, so a caller remounts
 * it via `key` on each open; `preserveState` keeps any dialog behind it intact.
 * Editing a category happens on its own page, so this is create-only.
 */
export function CategoryFormDialog({
    open,
    onOpenChange,
    initialType = 'income',
    onSaved,
}: Props) {
    const form = useForm({ name: '', description: '', type: initialType });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/configuration/categories', {
            preserveState: true,
            preserveScroll: true,
            only: ['categories'],
            onSuccess: (page) => {
                onSaved?.(page);
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Add category</DialogTitle>
                        <DialogDescription>
                            A label for classifying transactions. “Both” creates
                            a matching income and expense category.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="category-name">Name</Label>
                            <Input
                                id="category-name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                autoFocus
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Type</Label>
                            <div className="inline-flex rounded-md border p-0.5 text-sm">
                                {(['income', 'expense', 'both'] as const).map(
                                    (t) => (
                                        <button
                                            key={t}
                                            type="button"
                                            onClick={() =>
                                                form.setData('type', t)
                                            }
                                            className={cn(
                                                'flex-1 rounded px-3 py-1 capitalize',
                                                form.data.type === t &&
                                                    'bg-muted font-medium',
                                            )}
                                        >
                                            {t}
                                        </button>
                                    ),
                                )}
                            </div>
                            <InputError message={form.errors.type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="category-description">
                                Description
                            </Label>
                            <Textarea
                                id="category-description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                rows={3}
                                placeholder="Optional — what this category is for"
                            />
                            <InputError message={form.errors.description} />
                        </div>
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
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
