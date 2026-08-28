import { Head } from '@inertiajs/react';
import { CrudResource } from '@/components/crud/crud-resource';

type Category = {
    id: number;
    name: string;
    type: string;
};

export default function CategoriesIndex({
    categories,
}: {
    categories: Category[];
}) {
    return (
        <>
            <Head title="Categories" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title="Categories"
                    singular="category"
                    baseUrl="/categories"
                    items={categories}
                    columns={[
                        { key: 'name', label: 'Name' },
                        {
                            key: 'type',
                            label: 'Type',
                            render: (item) =>
                                String(item.type) === 'income'
                                    ? 'Income'
                                    : 'Expense',
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
                            key: 'type',
                            label: 'Type',
                            type: 'select',
                            options: [
                                { value: 'income', label: 'Income' },
                                { value: 'expense', label: 'Expense' },
                            ],
                        },
                    ]}
                    description="A label for classifying transactions, tied to income or expense."
                />
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: '/categories' }],
};
