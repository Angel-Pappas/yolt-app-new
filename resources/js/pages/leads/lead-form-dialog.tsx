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
type StatusOption = { id: number; name: string; is_conversion?: boolean };

export type EditableLead = {
    id: number;
    name: string;
    origin_id: number | null;
    status_id: number | null;
    website: string | null;
    contact_name: string | null;
    contact_position: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    contact_landline: string | null;
    description: string | null;
    next_step: string | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    statuses: StatusOption[];
    origins: Option[];
    editing?: EditableLead | null;
};

const NONE = 'none';

export function LeadFormDialog({
    open,
    onOpenChange,
    statuses,
    origins,
    editing,
}: Props) {
    const form = useForm({
        name: editing?.name ?? '',
        origin_id: editing?.origin_id ? String(editing.origin_id) : '',
        status_id: editing?.status_id ? String(editing.status_id) : '',
        website: editing?.website ?? '',
        contact_name: editing?.contact_name ?? '',
        contact_position: editing?.contact_position ?? '',
        contact_email: editing?.contact_email ?? '',
        contact_phone: editing?.contact_phone ?? '',
        contact_landline: editing?.contact_landline ?? '',
        description: editing?.description ?? '',
        next_step: editing?.next_step ?? '',
    });

    // Hide the flagged "Converted" status from the manual picker, but keep it if
    // it's the lead's current value (so saving doesn't silently clear the status).
    const pickableStatuses = statuses.filter(
        (s) => !s.is_conversion || String(s.id) === form.data.status_id,
    );

    function submit(e: FormEvent) {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            origin_id: data.origin_id || null,
            status_id: data.status_id || null,
        }));

        if (editing) {
            form.patch(`/leads/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            form.post('/leads', {
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
                            {editing ? 'Edit lead' : 'Add lead'}
                        </DialogTitle>
                        <DialogDescription>
                            A lead in the chasing pipeline, with its main
                            contact.
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
                            <Label htmlFor="origin_id">Origin</Label>
                            <Select
                                value={form.data.origin_id || NONE}
                                onValueChange={(v) =>
                                    form.setData(
                                        'origin_id',
                                        v === NONE ? '' : v,
                                    )
                                }
                            >
                                <SelectTrigger id="origin_id">
                                    <SelectValue placeholder="— None —" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        — None —
                                    </SelectItem>
                                    {origins.map((o) => (
                                        <SelectItem
                                            key={o.id}
                                            value={String(o.id)}
                                        >
                                            {o.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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
                                    {pickableStatuses.map((s) => (
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

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="website">Website</Label>
                            <Input
                                id="website"
                                value={form.data.website}
                                onChange={(e) =>
                                    form.setData('website', e.target.value)
                                }
                            />
                        </div>

                        <fieldset className="grid gap-4 rounded-lg border p-4 sm:col-span-2 sm:grid-cols-2">
                            <legend className="text-muted-foreground px-1 text-xs">
                                Main contact
                            </legend>
                            <div className="grid gap-2">
                                <Label htmlFor="contact_name">Name</Label>
                                <Input
                                    id="contact_name"
                                    value={form.data.contact_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'contact_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact_position">
                                    Position
                                </Label>
                                <Input
                                    id="contact_position"
                                    value={form.data.contact_position}
                                    onChange={(e) =>
                                        form.setData(
                                            'contact_position',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact_email">Email</Label>
                                <Input
                                    id="contact_email"
                                    value={form.data.contact_email}
                                    onChange={(e) =>
                                        form.setData(
                                            'contact_email',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact_phone">Phone</Label>
                                <Input
                                    id="contact_phone"
                                    value={form.data.contact_phone}
                                    onChange={(e) =>
                                        form.setData(
                                            'contact_phone',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact_landline">
                                    Landline
                                </Label>
                                <Input
                                    id="contact_landline"
                                    value={form.data.contact_landline}
                                    onChange={(e) =>
                                        form.setData(
                                            'contact_landline',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </fieldset>

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
