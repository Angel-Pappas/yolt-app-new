import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { EditableNextStep } from '@/components/inline-edit/editable-next-step';
import { EditableStatus } from '@/components/inline-edit/editable-status';
import { Button } from '@/components/ui/button';
import { formatAmount } from '@/lib/format';
import { type EditableProject, ProjectFormDialog } from './project-form-dialog';

type Related = { id: number; name: string } | null;
type LeadRef = { id: number; contact_name: string | null } | null;

type Project = EditableProject & {
    sort_order: number;
    status: Related;
    lead: LeadRef;
};

type Option = { id: number; name: string };

type Props = {
    projects: Project[];
    statuses: Option[];
};

export default function ProjectsIndex({ projects, statuses }: Props) {
    const statusOptions = statuses.map((s) => ({
        value: s.name,
        label: s.name,
    }));
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<EditableProject | null>(null);
    const [formKey, setFormKey] = useState(0);

    function openCreate() {
        setEditing(null);
        setFormKey((k) => k + 1);
        setDialogOpen(true);
    }

    function openEdit(project: Project) {
        setEditing(project);
        setFormKey((k) => k + 1);
        setDialogOpen(true);
    }

    function destroy(project: Project) {
        if (confirm('Delete this project?')) {
            router.delete(`/projects/${project.id}`, { preserveScroll: true });
        }
    }

    const columns: ColumnDef<Project>[] = [
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
            accessorKey: 'name',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <Link
                    href={`/projects/${row.original.id}`}
                    className="font-medium hover:underline"
                >
                    {row.original.name}
                </Link>
            ),
        },
        {
            id: 'client',
            accessorFn: (row) => row.lead?.contact_name ?? '',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Client" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.lead?.contact_name ?? '—'}
                </span>
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
                    options={statuses}
                    onSave={(v) =>
                        router.patch(
                            `/projects/${row.original.id}/status`,
                            { status_id: v || null },
                            { preserveScroll: true },
                        )
                    }
                />
            ),
        },
        {
            id: 'value',
            accessorFn: (row) => (row.value != null ? Number(row.value) : 0),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Value" align="right" />
            ),
            cell: ({ row }) =>
                row.original.value != null
                    ? formatAmount(row.original.value)
                    : '—',
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
                            `/projects/${row.original.id}/next-step`,
                            { next_step: v || null },
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
                        aria-label="Edit project"
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => destroy(row.original)}
                        aria-label="Delete project"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Projects" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    columns={columns}
                    data={projects}
                    title="Projects"
                    searchPlaceholder="Search projects…"
                    emptyMessage="No projects yet."
                    pageSize={50}
                    action={
                        <Button
                            onClick={openCreate}
                            size="icon"
                            aria-label="Add project"
                            title="Add project"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                />
            </div>

            <ProjectFormDialog
                key={formKey}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                editing={editing}
                statuses={statuses}
            />
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: '/projects' }],
};
