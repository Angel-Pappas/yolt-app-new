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

export type EditableContact = {
    id: number;
    name: string;
    position: string | null;
    phone: string | null;
    landline: string | null;
    website: string | null;
    email: string | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    leadId: number;
    editing?: EditableContact | null;
};

export function ContactFormDialog({
    open,
    onOpenChange,
    leadId,
    editing,
}: Props) {
    const form = useForm({
        name: editing?.name ?? '',
        position: editing?.position ?? '',
        phone: editing?.phone ?? '',
        landline: editing?.landline ?? '',
        website: editing?.website ?? '',
        email: editing?.email ?? '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();

        if (editing) {
            form.patch(`/leads/${leadId}/contacts/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            form.post(`/leads/${leadId}/contacts`, {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    form.reset();
                },
            });
        }
    }

    const fields: Array<{ key: keyof typeof form.data; label: string }> = [
        { key: 'position', label: 'Position' },
        { key: 'phone', label: 'Phone' },
        { key: 'landline', label: 'Landline' },
        { key: 'email', label: 'Email' },
        { key: 'website', label: 'Website' },
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit contact' : 'Add contact'}
                        </DialogTitle>
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

                        {fields.map((field) => (
                            <div className="grid gap-2" key={field.key}>
                                <Label htmlFor={field.key}>{field.label}</Label>
                                <Input
                                    id={field.key}
                                    value={form.data[field.key]}
                                    onChange={(e) =>
                                        form.setData(field.key, e.target.value)
                                    }
                                />
                            </div>
                        ))}
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
