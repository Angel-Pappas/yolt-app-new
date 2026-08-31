import { Head } from '@inertiajs/react';
import { CrudResource } from '@/components/crud/crud-resource';

type ProjectStatus = {
    id: number;
    name: string;
    position: number;
};

export default function ProjectStatusesIndex({
    projectStatuses,
}: {
    projectStatuses: ProjectStatus[];
}) {
    return (
        <>
            <Head title="Project statuses" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title="Project statuses"
                    singular="Project status"
                    baseUrl="/project-statuses"
                    items={projectStatuses}
                    columns={[{ key: 'name', label: 'Name' }]}
                    fields={[
                        {
                            key: 'name',
                            label: 'Name',
                            type: 'text',
                            required: true,
                        },
                    ]}
                    description="A stage a project can move through."
                />
            </div>
        </>
    );
}

ProjectStatusesIndex.layout = {
    breadcrumbs: [{ title: 'Project statuses', href: '/project-statuses' }],
};
