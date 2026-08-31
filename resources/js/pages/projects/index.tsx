import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-left">
                                <th className="p-3 font-medium">No.</th>
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">Client</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3 text-right font-medium">
                                    Value
                                </th>
                                <th className="p-3 font-medium">Next step</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {projects.map((project) => (
                                <tr key={project.id} className="border-t">
                                    <td className="text-muted-foreground p-3 tabular-nums">
                                        {project.sort_order}
                                    </td>
                                    <td className="p-3 font-medium">
                                        {project.name}
                                    </td>
                                    <td className="text-muted-foreground p-3">
                                        {project.lead?.contact_name ?? '—'}
                                    </td>
                                    <td className="p-3 whitespace-nowrap">
                                        {project.status?.name ?? '—'}
                                    </td>
                                    <td className="p-3 text-right tabular-nums">
                                        {project.value != null
                                            ? formatAmount(project.value)
                                            : '—'}
                                    </td>
                                    <td className="text-muted-foreground max-w-xs p-3">
                                        {project.next_step || '—'}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    openEdit(project)
                                                }
                                                aria-label="Edit project"
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => destroy(project)}
                                                aria-label="Delete project"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {projects.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground p-6 text-center"
                                    >
                                        No projects yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
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
