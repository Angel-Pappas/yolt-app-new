import { Head, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
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

    const columns: ColumnDef<Entity>[] = [
        {
            accessorKey: 'name',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            accessorKey: 'vat_number',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="VAT number" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground tabular-nums">
                    {row.original.vat_number ?? '—'}
                </span>
            ),
        },
        {
            id: 'actions',
            enableSorting: false,
            meta: { align: 'right' },
            header: () => null,
            cell: ({ row }) => (
                <div className="flex justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(row.original)}
                        aria-label={`Edit ${row.original.name}`}
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => destroy(row.original)}
                        aria-label={`Delete ${row.original.name}`}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Entities" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    columns={columns}
                    data={entities}
                    title="Entities"
                    searchPlaceholder="Search entities…"
                    emptyMessage="No entities yet."
                    pageSize={50}
                    action={
                        <Button
                            onClick={openCreate}
                            size="icon"
                            aria-label="Add entity"
                            title="Add entity"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                />
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
