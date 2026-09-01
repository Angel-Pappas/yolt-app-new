import { Head, Link, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { formatAmount } from '@/lib/format';
import { type EditableProject, ProjectFormDialog } from './project-form-dialog';
import { type ProjectFilters, ProjectsFilters } from './projects-filters';

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
    filters: ProjectFilters;
    statuses: Option[];
};

export default function ProjectsIndex({ projects, filters, statuses }: Props) {
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
            id: 'value',
            accessorFn: (row) => (row.value != null ? Number(row.value) : 0),
            meta: { align: 'right' },
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
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Projects</h1>
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        Add project
                    </Button>
                </div>

                <ProjectsFilters filters={filters} statuses={statuses} />

                <DataTable
                    columns={columns}
                    data={projects}
                    emptyMessage="No projects yet."
                    pageSize={50}
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
