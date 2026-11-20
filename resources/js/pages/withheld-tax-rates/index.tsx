import { Head, router } from '@inertiajs/react';
import { CrudResource } from '@/components/crud/crud-resource';

type WithheldRate = {
    id: number;
    name: string;
    rate: string;
};

export default function WithheldTaxRatesIndex({
    withheldRates,
}: {
    withheldRates: WithheldRate[];
}) {
    return (
        <>
            <Head title="Withheld tax rates" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title="Withheld tax rates"
                    singular="withheld rate"
                    baseUrl="/configuration/withheld-tax-rates"
                    items={withheldRates}
                    columns={[
                        {
                            key: 'name',
                            label: 'Name',
                            filter: { type: 'text' },
                        },
                        {
                            key: 'rate',
                            label: 'Rate',
                            align: 'right',
                            filter: { type: 'number' },
                            render: (item) =>
                                `${Number(item.rate).toFixed(2)}%`,
                        },
                    ]}
                    // A rate's page holds its dated changes.
                    onRowClick={(item) =>
                        router.visit(
                            `/configuration/withheld-tax-rates/${item.id}`,
                        )
                    }
                    rowHref={(item) =>
                        `/configuration/withheld-tax-rates/${item.id}`
                    }
                    fields={[
                        {
                            key: 'name',
                            label: 'Name',
                            type: 'text',
                            required: true,
                        },
                        {
                            key: 'rate',
                            label: 'Rate (%)',
                            type: 'decimal',
                            required: true,
                        },
                    ]}
                    description="A withholding-tax percentage a transaction's withheld line can use."
                />
            </div>
        </>
    );
}
