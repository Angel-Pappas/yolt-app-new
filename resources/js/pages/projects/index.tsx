import { Head, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import {
    ProjectFields,
    projectFormData,
    projectPayload,
    type ProjectRow,
} from '@/components/projects/project-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatAmount } from '@/lib/format';

const columns: ColumnDef<ProjectRow>[] = [
    {
        id: 'name',
        accessorFn: (r) => r.name,
        meta: { filter: { type: 'text' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Project" />
        ),
        cell: ({ row }) => (
            <span className="font-medium">{row.original.name}</span>
        ),
    },
    {
        id: 'customer',
        accessorFn: (r) => r.entity.name,
        meta: { filter: { type: 'select' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Customer" />
        ),
    },
    {
        id: 'deliverables',
        accessorFn: (r) => r.deliverables.map((d) => d.name).join(', '),
        meta: { filter: { type: 'text' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Deliverables" />
        ),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {row.original.deliverables.map((d) => d.name).join(', ')}
            </span>
        ),
    },
    {
        id: 'value',
        accessorFn: (r) => r.value,
        meta: { align: 'right', filter: { type: 'number' } },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Value" align="right" />
        ),
        cell: ({ row }) => formatAmount(row.original.value),
        footer: ({ table }) =>
            formatAmount(
                table
                    .getFilteredRowModel()
                    .rows.reduce((s, r) => s + r.original.value, 0),
            ),
    },
];

/**
 * Finance ▸ Projects: every project with its customer and value (the sum of its
 * deliverables, net). A row opens the project's page; "+" adds one. The money a
 * project brings is planned on its customer (Payments).
 */
export default function ProjectsIndex({
    projects,
}: {
    projects: ProjectRow[];
}) {
    const [open, setOpen] = useState(false);
    const [formKey, setFormKey] = useState(0);

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
                    enableTotals
                    onRowClick={(p) => router.visit(`/projects/${p.id}`)}
                    rowHref={(p) => `/projects/${p.id}`}
                    action={
                        <Button
                            size="icon"
                            onClick={() => {
                                setFormKey((k) => k + 1);
                                setOpen(true);
                            }}
                            aria-label="Add project"
                            title="Add project"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                />
            </div>
            <AddProjectDialog
                key={formKey}
                open={open}
                onOpenChange={setOpen}
            />
        </>
    );
}

function AddProjectDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm(projectFormData());

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform(projectPayload);
        form.post('/projects', { onSuccess: () => onOpenChange(false) });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <form onSubmit={submit} className="contents">
                    <DialogHeader>
                        <DialogTitle>Add project</DialogTitle>
                        <DialogDescription>
                            Its value is the sum of its deliverables. What the
                            customer pays, and when, goes in the customer's
                            Payments.
                        </DialogDescription>
                    </DialogHeader>
                    <ProjectFields form={form} />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: '/projects' }],
};
