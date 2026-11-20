import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { type FormEvent } from 'react';
import {
    ProjectFields,
    projectFormData,
    projectPayload,
    type ProjectRow,
} from '@/components/projects/project-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

/**
 * A project's own page: its name, customer, deliverables and notes, edited in
 * place. The money it brings is planned on the customer — the link goes to the
 * customer's page.
 */
export default function ProjectShow({ project }: { project: ProjectRow }) {
    const form = useForm(projectFormData(project));

    function save(e: FormEvent) {
        e.preventDefault();
        form.transform(projectPayload);
        form.patch(`/projects/${project.id}`, { preserveScroll: true });
    }

    function destroy() {
        if (confirm('Delete this project?')) {
            router.delete(`/projects/${project.id}`);
        }
    }

    return (
        <>
            <Head title={project.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/projects" aria-label="Back to Projects">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold">{project.name}</h1>
                    <Link
                        href={`/entities/${project.entity.id}?tab=payments`}
                        className="text-muted-foreground text-sm hover:underline"
                    >
                        {project.entity.name} · payments
                    </Link>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="ml-auto"
                        onClick={destroy}
                        aria-label="Delete project"
                        title="Delete project"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
                <Card>
                    <CardContent>
                        <form onSubmit={save} className="grid gap-4">
                            <ProjectFields form={form} />
                            <div>
                                <Button
                                    type="submit"
                                    disabled={form.processing || !form.isDirty}
                                >
                                    Save
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
