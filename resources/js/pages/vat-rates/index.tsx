import { Head } from '@inertiajs/react';
import { CrudResource } from '@/components/crud/crud-resource';

type VatRate = {
    id: number;
    name: string;
    rate: string;
};

export default function VatRatesIndex({ vatRates }: { vatRates: VatRate[] }) {
    return (
        <>
            <Head title="VAT rates" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title="VAT rates"
                    singular="VAT rate"
                    baseUrl="/vat-rates"
                    items={vatRates}
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
                    description="A VAT percentage a transaction line can use."
                />
            </div>
        </>
    );
}

VatRatesIndex.layout = {
    breadcrumbs: [{ title: 'VAT rates', href: '/vat-rates' }],
};
