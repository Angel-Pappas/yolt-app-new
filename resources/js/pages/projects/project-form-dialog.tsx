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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Option = { id: number; name: string };

export type EditableProject = {
    id: number;
    name: string;
    status_id: number | null;
    description: string | null;
    value: string | null;
    estimated_months: number | null;
    next_step: string | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    statuses: Option[];
    editing?: EditableProject | null;
};

const NONE = 'none';

export function ProjectFormDialog({
    open,
    onOpenChange,
    statuses,
    editing,
}: Props) {
    const form = useForm({
        name: editing?.name ?? '',
        status_id: editing?.status_id ? String(editing.status_id) : '',
        value: editing?.value ?? '',
        estimated_months:
            editing?.estimated_months != null
                ? String(editing.estimated_months)
                : '',
        description: editing?.description ?? '',
        next_step: editing?.next_step ?? '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            status_id: data.status_id || null,
            value: data.value ? String(data.value).replace(',', '.') : null,
            estimated_months: data.estimated_months || null,
        }));

        if (editing) {
            form.patch(`/projects/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            form.post('/projects', {
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
            <DialogContent className="sm:max-w-2xl">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit project' : 'Add project'}
                        </DialogTitle>
                        <DialogDescription>
                            Project info only — client details stay on the lead.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status_id">Status</Label>
                            <Select
                                value={form.data.status_id || NONE}
                                onValueChange={(v) =>
                                    form.setData(
                                        'status_id',
                                        v === NONE ? '' : v,
                                    )
                                }
                            >
                                <SelectTrigger id="status_id">
                                    <SelectValue placeholder="— None —" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        — None —
                                    </SelectItem>
                                    {statuses.map((s) => (
                                        <SelectItem
                                            key={s.id}
                                            value={String(s.id)}
                                        >
                                            {s.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="value">Value (€)</Label>
                            <Input
                                id="value"
                                inputMode="decimal"
                                value={form.data.value}
                                onChange={(e) =>
                                    form.setData('value', e.target.value)
                                }
                            />
                            <InputError message={form.errors.value} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="estimated_months">
                                Estimated duration (months)
                            </Label>
                            <Input
                                id="estimated_months"
                                inputMode="numeric"
                                value={form.data.estimated_months}
                                onChange={(e) =>
                                    form.setData(
                                        'estimated_months',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.estimated_months}
                            />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="next_step">Next step</Label>
                            <Textarea
                                id="next_step"
                                value={form.data.next_step}
                                onChange={(e) =>
                                    form.setData('next_step', e.target.value)
                                }
                            />
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
                            {editing ? 'Save' : 'Add'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
