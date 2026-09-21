import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    type CrudField,
    type CrudItem,
    CrudResource,
} from '@/components/crud/crud-resource';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

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

/** The Cheese bulk-actions bar: pick a type and move every selected entity to it. */
function CheeseBulkBar({
    selected,
    clear,
}: {
    selected: CrudItem[];
    clear: () => void;
}) {
    const [type, setType] = useState('');

    function assign() {
        if (!type) return;
        router.patch(
            '/entities/bulk/type',
            { ids: selected.map((e) => e.id), type },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clear();
                    setType('');
                },
            },
        );
    }

    return (
        <>
            <Select value={type} onValueChange={setType}>
                <SelectTrigger className="h-8 w-44" aria-label="Assign type">
                    <SelectValue placeholder="Assign type…" />
                </SelectTrigger>
                <SelectContent>
                    {TYPE_OPTIONS.map((o) => (
                        <SelectItem key={o.value} value={o.value}>
                            {o.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button size="sm" disabled={!type} onClick={assign}>
                Assign
            </Button>
        </>
    );
}

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
                    onRowClick={(item: CrudItem) =>
                        router.visit(`/entities/${item.id}`)
                    }
                    disableEdit={!classify}
                    enableSelection={classify}
                    getRowId={(item: CrudItem) => String(item.id)}
                    renderBulkActions={
                        classify
                            ? (selected, clear) => (
                                  <CheeseBulkBar
                                      selected={selected}
                                      clear={clear}
                                  />
                              )
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
