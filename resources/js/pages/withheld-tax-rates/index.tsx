import { Head } from '@inertiajs/react';
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
                    baseUrl="/withheld-tax-rates"
                    items={withheldRates}
                    columns={[
                        { key: 'name', label: 'Name' },
                        {
                            key: 'rate',
                            label: 'Rate',
                            align: 'right',
                            render: (item) =>
                                `${Number(item.rate).toFixed(2)}%`,
                        },
                    ]}
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

WithheldTaxRatesIndex.layout = {
    breadcrumbs: [{ title: 'Withheld tax rates', href: '/withheld-tax-rates' }],
};
