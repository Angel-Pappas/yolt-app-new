import { Head } from '@inertiajs/react';
import { CrudResource } from '@/components/crud/crud-resource';

type LeadStatus = {
    id: number;
    name: string;
    position: number;
};

export default function LeadStatusesIndex({
    leadStatuses,
}: {
    leadStatuses: LeadStatus[];
}) {
    return (
        <>
            <Head title="Lead statuses" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title="Lead statuses"
                    singular="Lead status"
                    baseUrl="/lead-statuses"
                    items={leadStatuses}
                    columns={[{ key: 'name', label: 'Name' }]}
                    fields={[
                        {
                            key: 'name',
                            label: 'Name',
                            type: 'text',
                            required: true,
                        },
                    ]}
                    description="A pipeline stage a lead can move through."
                />
            </div>
        </>
    );
}

LeadStatusesIndex.layout = {
    breadcrumbs: [{ title: 'Lead statuses', href: '/lead-statuses' }],
};
