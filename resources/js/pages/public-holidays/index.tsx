import { Head } from '@inertiajs/react';
import { CrudResource } from '@/components/crud/crud-resource';
import { formatDate } from '@/lib/format';

type Holiday = {
    id: number;
    date: string;
    name: string | null;
};

export default function PublicHolidaysIndex({
    holidays,
}: {
    holidays: Holiday[];
}) {
    return (
        <>
            <Head title="Public holidays" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title="Public holidays"
                    singular="public holiday"
                    baseUrl="/configuration/public-holidays"
                    items={holidays}
                    columns={[
                        {
                            key: 'date',
                            label: 'Date',
                            filter: { type: 'date' },
                            render: (item) => formatDate(String(item.date)),
                        },
                        {
                            key: 'name',
                            label: 'Name',
                            filter: { type: 'text' },
                        },
                    ]}
                    fields={[
                        {
                            key: 'date',
                            label: 'Date',
                            type: 'date',
                            required: true,
                        },
                        {
                            key: 'name',
                            label: 'Name (optional)',
                            type: 'text',
                        },
                    ]}
                    description="A holiday date. With weekends, it shifts a tax's payment date to the previous working day."
                />
            </div>
        </>
    );
}

PublicHolidaysIndex.layout = {
    breadcrumbs: [
        {
            title: 'Public holidays',
            href: '/configuration/public-holidays',
        },
    ],
};
