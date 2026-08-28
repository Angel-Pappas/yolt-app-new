import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
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

type Entity = {
    id: number;
    name: string;
    vat_number: string | null;
};

export default function EntitiesIndex({ entities }: { entities: Entity[] }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Entity | null>(null);

    const form = useForm({ name: '', vat_number: '' });

    function openCreate() {
        setEditing(null);
        form.setData({ name: '', vat_number: '' });
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(entity: Entity) {
        setEditing(entity);
        form.setData({
            name: entity.name,
            vat_number: entity.vat_number ?? '',
        });
        form.clearErrors();
        setOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        const options = {
            onSuccess: () => setOpen(false),
            preserveScroll: true,
        };

        if (editing) {
            form.patch(`/entities/${editing.id}`, options);
        } else {
            form.post('/entities', options);
        }
    }

    function destroy(entity: Entity) {
        if (confirm(`Delete entity "${entity.name}"?`)) {
            router.delete(`/entities/${entity.id}`, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Entities" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Entities</h1>
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        Add entity
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-left">
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">VAT number</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {entities.map((entity) => (
                                <tr key={entity.id} className="border-t">
                                    <td className="p-3 font-medium">
                                        {entity.name}
                                    </td>
                                    <td className="text-muted-foreground p-3 tabular-nums">
                                        {entity.vat_number ?? '—'}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => openEdit(entity)}
                                                aria-label={`Edit ${entity.name}`}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => destroy(entity)}
                                                aria-label={`Delete ${entity.name}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {entities.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="text-muted-foreground p-6 text-center"
                                    >
                                        No entities yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Edit entity' : 'Add entity'}
                            </DialogTitle>
                            <DialogDescription>
                                A counterparty — a supplier, a customer, the
                                state, and so on.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
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
                                <Label htmlFor="vat_number">
                                    VAT number (optional)
                                </Label>
                                <Input
                                    id="vat_number"
                                    value={form.data.vat_number}
                                    onChange={(e) =>
                                        form.setData(
                                            'vat_number',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.vat_number} />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
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
        </>
    );
}

EntitiesIndex.layout = {
    breadcrumbs: [{ title: 'Entities', href: '/entities' }],
};
