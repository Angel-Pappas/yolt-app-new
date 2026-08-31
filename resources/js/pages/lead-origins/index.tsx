import { Head } from '@inertiajs/react';
import { CrudResource } from '@/components/crud/crud-resource';

type LeadOrigin = {
    id: number;
    name: string;
    position: number;
};

export default function LeadOriginsIndex({
    leadOrigins,
}: {
    leadOrigins: LeadOrigin[];
}) {
    return (
        <>
            <Head title="Lead origins" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title="Lead origins"
                    singular="Lead origin"
                    baseUrl="/lead-origins"
                    items={leadOrigins}
                    columns={[{ key: 'name', label: 'Name' }]}
                    fields={[
                        {
                            key: 'name',
                            label: 'Name',
                            type: 'text',
                            required: true,
                        },
                    ]}
                    description="Where a lead came from."
                />
            </div>
        </>
    );
}

LeadOriginsIndex.layout = {
    breadcrumbs: [{ title: 'Lead origins', href: '/lead-origins' }],
};
