import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type EditableAction = {
    id: number;
    action_date: string;
    body: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Collection URL for the parent's actions, e.g. `/leads/12/actions`. */
    baseUrl: string;
    editing?: EditableAction | null;
};

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

/**
 * The History activity-log entry dialog, shared by Leads and Projects. `baseUrl`
 * is the parent's actions collection; edits post to `${baseUrl}/${id}`.
 */
export function ActionFormDialog({
    open,
    onOpenChange,
    baseUrl,
    editing,
}: Props) {
    const form = useForm({
        action_date: editing?.action_date.slice(0, 10) ?? today(),
        body: editing?.body ?? '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.patch(`${baseUrl}/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            form.post(baseUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    form.reset();
                },
            });
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit action' : 'Log action'}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="action_date">Date</Label>
                            <Input
                                id="action_date"
                                type="date"
                                value={form.data.action_date}
                                onChange={(e) =>
                                    form.setData('action_date', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.action_date} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="body">Action</Label>
                            <Textarea
                                id="body"
                                value={form.data.body}
                                onChange={(e) =>
                                    form.setData('body', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.body} />
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
                            {editing ? 'Save' : 'Log'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
