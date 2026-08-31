import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    type EditableAction,
    ActionFormDialog,
} from '@/components/crm/action-form-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatAmount, formatDate } from '@/lib/format';
import { type EditableProject, ProjectFormDialog } from './project-form-dialog';

type Related = { id: number; name: string } | null;
type LeadRef = { id: number; contact_name: string | null } | null;

type Project = EditableProject & {
    sort_order: number;
    lead_id: number | null;
    status: Related;
    lead: LeadRef;
};

type Action = {
    id: number;
    action_date: string;
    body: string;
    author_name: string | null;
};

type Option = { id: number; name: string };

type Props = {
    project: Project;
    actions: Action[];
    statuses: Option[];
};

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <div className="text-muted-foreground text-xs">{label}</div>
            <div>{value || '—'}</div>
        </div>
    );
}

export default function ProjectShow({ project, actions, statuses }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [editKey, setEditKey] = useState(0);
    const [actionOpen, setActionOpen] = useState(false);
    const [editingAction, setEditingAction] = useState<EditableAction | null>(
        null,
    );
    const [actionKey, setActionKey] = useState(0);

    function openAddAction() {
        setEditingAction(null);
        setActionKey((k) => k + 1);
        setActionOpen(true);
    }

    function openEditAction(action: Action) {
        setEditingAction(action);
        setActionKey((k) => k + 1);
        setActionOpen(true);
    }

    function destroyAction(action: Action) {
        if (confirm('Delete this action?')) {
            router.delete(`/projects/${project.id}/actions/${action.id}`, {
                preserveScroll: true,
            });
        }
    }

    return (
        <>
            <Head title={project.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="ghost" size="icon" asChild>
                            <Link
                                href="/projects"
                                aria-label="Back to projects"
                            >
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold">
                                {project.name}
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                No. {project.sort_order}
                                {project.lead?.contact_name
                                    ? ` · ${project.lead.contact_name}`
                                    : ''}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {project.lead_id && (
                            <Button variant="ghost" asChild>
                                <Link href={`/leads/${project.lead_id}`}>
                                    View lead →
                                </Link>
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => {
                                setEditKey((k) => k + 1);
                                setEditOpen(true);
                            }}
                        >
                            <Pencil className="size-4" />
                            Edit project
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        <Detail
                            label="Status"
                            value={project.status?.name ?? null}
                        />
                        <Detail
                            label="Value"
                            value={
                                project.value != null
                                    ? formatAmount(project.value)
                                    : null
                            }
                        />
                        <Detail
                            label="Estimated duration"
                            value={
                                project.estimated_months != null
                                    ? `${project.estimated_months} months`
                                    : null
                            }
                        />
                        <div className="sm:col-span-3">
                            <Detail
                                label="Description"
                                value={project.description}
                            />
                        </div>
                        <div className="sm:col-span-3">
                            <Detail
                                label="Next step"
                                value={project.next_step}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle>History</CardTitle>
                        <Button size="sm" onClick={openAddAction}>
                            <Plus className="size-4" />
                            Log action
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground text-left">
                                        <th className="pb-2 font-medium">
                                            Date
                                        </th>
                                        <th className="pb-2 font-medium">
                                            User
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Action
                                        </th>
                                        <th className="pb-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {actions.map((action) => (
                                        <tr
                                            key={action.id}
                                            className="border-t"
                                        >
                                            <td className="py-2 whitespace-nowrap tabular-nums">
                                                {formatDate(action.action_date)}
                                            </td>
                                            <td className="text-muted-foreground py-2 whitespace-nowrap">
                                                {action.author_name ?? '—'}
                                            </td>
                                            <td className="py-2 whitespace-pre-wrap">
                                                {action.body}
                                            </td>
                                            <td className="py-2">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEditAction(
                                                                action,
                                                            )
                                                        }
                                                        aria-label="Edit action"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            destroyAction(
                                                                action,
                                                            )
                                                        }
                                                        aria-label="Delete action"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {actions.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="text-muted-foreground py-4 text-center"
                                            >
                                                No actions logged yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <ProjectFormDialog
                key={editKey}
                open={editOpen}
                onOpenChange={setEditOpen}
                editing={project}
                statuses={statuses}
            />

            <ActionFormDialog
                key={actionKey}
                open={actionOpen}
                onOpenChange={setActionOpen}
                baseUrl={`/projects/${project.id}/actions`}
                editing={editingAction}
            />
        </>
    );
}

ProjectShow.layout = {
    breadcrumbs: [{ title: 'Projects', href: '/projects' }],
};
