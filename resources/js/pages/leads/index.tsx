import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { EditableNextStep } from '@/components/inline-edit/editable-next-step';
import { EditableStatus } from '@/components/inline-edit/editable-status';
import { Button } from '@/components/ui/button';
import { formatPhone } from '@/lib/format';
import { type EditableLead, LeadFormDialog } from './lead-form-dialog';

type Related = { id: number; name: string } | null;

type Lead = EditableLead & {
    sort_order: number;
    origin: Related;
    status: Related;
};

type Option = { id: number; name: string };
type StatusOption = { id: number; name: string; is_conversion?: boolean };

type Props = {
    leads: Lead[];
    statuses: StatusOption[];
    origins: Option[];
};

export default function LeadsIndex({ leads, statuses, origins }: Props) {
    const originOptions = origins.map((o) => ({
        value: o.name,
        label: o.name,
    }));
    const statusOptions = statuses
        .filter((s) => !s.is_conversion)
        .map((s) => ({ value: s.name, label: s.name }));
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<EditableLead | null>(null);
    const [formKey, setFormKey] = useState(0);

    function openCreate() {
        setEditing(null);
        setFormKey((k) => k + 1);
        setDialogOpen(true);
    }

    function openEdit(lead: Lead) {
        setEditing(lead);
        setFormKey((k) => k + 1);
        setDialogOpen(true);
    }

    function destroy(lead: Lead) {
        if (confirm('Delete this lead?')) {
            router.delete(`/leads/${lead.id}`, { preserveScroll: true });
        }
    }

    const columns: ColumnDef<Lead>[] = [
        {
            accessorKey: 'sort_order',
            meta: { filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="No." />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground tabular-nums">
                    {row.original.sort_order}
                </span>
            ),
        },
        {
            id: 'origin',
            accessorFn: (row) => row.origin?.name ?? '',
            meta: { filter: { type: 'select', options: originOptions } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Origin" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap">
                    {row.original.origin?.name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'name',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <Link
                    href={`/leads/${row.original.id}`}
                    className="font-medium hover:underline"
                >
                    {row.original.name}
                </Link>
            ),
        },
        {
            accessorKey: 'contact_email',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Email" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.contact_email || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'contact_phone',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Phone" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap">
                    {formatPhone(row.original.contact_phone) || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'next_step',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Next step" />
            ),
            cell: ({ row }) => (
                <EditableNextStep
                    value={row.original.next_step}
                    onSave={(v) =>
                        router.patch(
                            `/leads/${row.original.id}/next-step`,
                            { next_step: v || null },
                            { preserveScroll: true },
                        )
                    }
                />
            ),
        },
        {
            id: 'status',
            accessorFn: (row) => row.status?.name ?? '',
            meta: { filter: { type: 'select', options: statusOptions } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <EditableStatus
                    value={row.original.status?.id ?? null}
                    options={statuses.filter((s) => !s.is_conversion)}
                    onSave={(v) =>
                        router.patch(
                            `/leads/${row.original.id}/status`,
                            { status_id: v || null },
                            { preserveScroll: true },
                        )
                    }
                />
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
                        aria-label="Edit lead"
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => destroy(row.original)}
                        aria-label="Delete lead"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Leads" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    columns={columns}
                    data={leads}
                    title="Leads"
                    searchPlaceholder="Search leads…"
                    emptyMessage="No leads yet."
                    pageSize={50}
                    action={
                        <Button
                            onClick={openCreate}
                            size="icon"
                            aria-label="Add lead"
                            title="Add lead"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                />
            </div>

            <LeadFormDialog
                key={formKey}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                editing={editing}
                statuses={statuses}
                origins={origins}
            />
        </>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'Leads', href: '/leads' }],
};
