import { Head, router } from '@inertiajs/react';
import {
    type CrudField,
    type CrudItem,
    CrudResource,
} from '@/components/crud/crud-resource';

type Entity = {
    id: number;
    name: string;
    type: string;
    vat_number: string | null;
};

type Props = {
    entities: Entity[];
    title: string;
    singular: string;
};

export default function EntitiesList({ entities, title, singular }: Props) {
    const fields: CrudField[] = [
        { key: 'name', label: 'Name', type: 'text', required: true },
        { key: 'vat_number', label: 'VAT number (optional)', type: 'text' },
    ];

    return (
        <>
            <Head title={title} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title={title}
                    singular={singular}
                    baseUrl="/entities"
                    items={entities}
                    columns={[
                        {
                            key: 'name',
                            label: 'Name',
                            filter: { type: 'text' },
                            render: (item) => (
                                <span className="font-medium">
                                    {String(item.name)}
                                </span>
                            ),
                        },
                        {
                            key: 'vat_number',
                            label: 'VAT number',
                            filter: { type: 'text' },
                            render: (item) => (
                                <span className="text-muted-foreground tabular-nums">
                                    {item.vat_number
                                        ? String(item.vat_number)
                                        : '—'}
                                </span>
                            ),
                        },
                    ]}
                    fields={fields}
                    fixedValues={{ type: singular }}
                    onRowClick={(item: CrudItem) =>
                        router.visit(`/entities/${item.id}`)
                    }
                    disableEdit
                />
            </div>
        </>
    );
}

EntitiesList.layout = {
    breadcrumbs: [{ title: 'Entities', href: '/entities/customers' }],
};
