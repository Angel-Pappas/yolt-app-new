import { Head } from '@inertiajs/react';
import { type CrudField, CrudResource } from '@/components/crud/crud-resource';

type Entity = {
    id: number;
    name: string;
    type: string | null;
    vat_number: string | null;
};

type Props = {
    entities: Entity[];
    title: string;
    singular: string;
    /** The Cheese bucket — show a Type picker so each entity can be classified. */
    classify: boolean;
};

const TYPE_OPTIONS = [
    { value: 'customer', label: 'Customer' },
    { value: 'supplier', label: 'Supplier' },
    { value: 'contractor', label: 'Contractor' },
    { value: 'employee', label: 'Employee' },
];

export default function EntitiesList({
    entities,
    title,
    singular,
    classify,
}: Props) {
    const fields: CrudField[] = [
        { key: 'name', label: 'Name', type: 'text', required: true },
        ...(classify
            ? [
                  {
                      key: 'type',
                      label: 'Type',
                      type: 'select' as const,
                      options: TYPE_OPTIONS,
                      placeholder: 'Choose a type',
                  },
              ]
            : []),
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
                    fixedValues={classify ? {} : { type: singular }}
                    description={
                        classify
                            ? 'Unclassified entities. Assign each a type to move it into the right list.'
                            : undefined
                    }
                />
            </div>
        </>
    );
}

EntitiesList.layout = {
    breadcrumbs: [{ title: 'Entities', href: '/entities/customers' }],
};
