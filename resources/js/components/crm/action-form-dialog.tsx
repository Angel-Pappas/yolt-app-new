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
import { DateField } from '@/components/ui/date-field';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export type EditableAction = {
    id: number;
    action_date: string;
    body: string;
    user_id?: number | null;
};

type UserOption = { id: number; name: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Collection URL for the parent's actions, e.g. `/leads/12/actions`. */
    baseUrl: string;
    editing?: EditableAction | null;
    /** Non-empty only for admins — lets them attribute the action to a colleague. */
    users?: UserOption[];
    currentUserId: number;
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
    users = [],
    currentUserId,
}: Props) {
    const form = useForm({
        action_date: editing?.action_date.slice(0, 10) ?? today(),
        body: editing?.body ?? '',
        user_id: String(editing?.user_id ?? currentUserId),
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
                            <DateField
                                id="action_date"
                                value={form.data.action_date}
                                showCalendar={false}
                                required
                                onChange={(iso) =>
                                    form.setData('action_date', iso)
                                }
                            />
                            <InputError message={form.errors.action_date} />
                        </div>

                        {users.length > 0 && (
                            <div className="grid gap-2">
                                <Label htmlFor="user_id">User</Label>
                                <Select
                                    value={form.data.user_id}
                                    onValueChange={(v) =>
                                        form.setData('user_id', v)
                                    }
                                >
                                    <SelectTrigger id="user_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {users.map((u) => (
                                            <SelectItem
                                                key={u.id}
                                                value={String(u.id)}
                                            >
                                                {u.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

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
