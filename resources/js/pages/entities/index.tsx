import { Head } from '@inertiajs/react';
import { CrudResource } from '@/components/crud/crud-resource';
import { entityDescription, entityFields } from './entity-fields';

type Entity = {
    id: number;
    name: string;
    vat_number: string | null;
};

export default function EntitiesIndex({ entities }: { entities: Entity[] }) {
    return (
        <>
            <Head title="Entities" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title="Entities"
                    singular="entity"
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
                    fields={entityFields}
                    description={entityDescription}
                />
            </div>
        </>
    );
}

EntitiesIndex.layout = {
    breadcrumbs: [{ title: 'Entities', href: '/entities' }],
};
