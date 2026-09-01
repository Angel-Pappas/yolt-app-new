import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { type EditableLead, LeadFormDialog } from './lead-form-dialog';
import { type LeadFilters, LeadsFilters } from './leads-filters';

type Related = { id: number; name: string } | null;

type Lead = EditableLead & {
    sort_order: number;
    origin: Related;
    status: Related;
};

type Option = { id: number; name: string };

type Props = {
    leads: Lead[];
    filters: LeadFilters;
    statuses: Option[];
    origins: Option[];
};

export default function LeadsIndex({
    leads,
    filters,
    statuses,
    origins,
}: Props) {
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
            header: ({ column }) => (
                <ColumnHeader column={column} title="Phone" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap">
                    {row.original.contact_phone || '—'}
                </span>
            ),
        },
        {
            accessorKey: 'next_step',
            header: ({ column }) => (
                <ColumnHeader column={column} title="Next step" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.next_step || '—'}
                </span>
            ),
        },
        {
            id: 'status',
            accessorFn: (row) => row.status?.name ?? '',
            header: ({ column }) => (
                <ColumnHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <span className="whitespace-nowrap">
                    {row.original.status?.name ?? '—'}
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
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Leads</h1>
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        Add lead
                    </Button>
                </div>

                <LeadsFilters
                    filters={filters}
                    statuses={statuses}
                    origins={origins}
                />

                <DataTable
                    columns={columns}
                    data={leads}
                    emptyMessage="No leads yet."
                    pageSize={50}
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
