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
    const nameColumn = [
        { key: 'name', label: 'Name', filter: { type: 'text' as const } },
    ];
    const nameField = [
        { key: 'name', label: 'Name', type: 'text' as const, required: true },
    ];

    return (
        <>
            <Head title="Categories" />
            <div className="grid h-full flex-1 gap-6 p-4 lg:grid-cols-2">
                <CrudResource
                    title="Income categories"
                    singular="income category"
                    baseUrl="/categories"
                    items={categories.filter((c) => c.type === 'income')}
                    columns={nameColumn}
                    fields={nameField}
                    fixedValues={{ type: 'income' }}
                    description="A label for classifying income transactions."
                />
                <CrudResource
                    title="Expense categories"
                    singular="expense category"
                    baseUrl="/categories"
                    items={categories.filter((c) => c.type === 'expense')}
                    columns={nameColumn}
                    fields={nameField}
                    fixedValues={{ type: 'expense' }}
                    description="A label for classifying expense transactions."
                />
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: '/categories' }],
};
