import { Head, router } from '@inertiajs/react';
import { type CrudColumn, CrudResource } from '@/components/crud/crud-resource';

type Category = {
    id: number;
    name: string;
    type: string;
    description: string | null;
};

export default function CategoriesIndex({
    categories,
}: {
    categories: Category[];
}) {
    // Name over a gray description line (matches the transactions two-line cells).
    const nameColumn: CrudColumn[] = [
        {
            key: 'name',
            label: 'Name',
            filter: { type: 'text' },
            render: (item) => (
                <div>
                    <div>{String(item.name)}</div>
                    {item.description ? (
                        <div className="text-muted-foreground text-sm">
                            {String(item.description)}
                        </div>
                    ) : null}
                </div>
            ),
        },
    ];
    const fields = [
        { key: 'name', label: 'Name', type: 'text' as const, required: true },
        {
            key: 'description',
            label: 'Description',
            type: 'textarea' as const,
            placeholder: 'Optional — what this category is for',
        },
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
                    fields={fields}
                    fixedValues={{ type: 'income' }}
                    onRowClick={(item) =>
                        router.visit(`/categories/${item.id}`)
                    }
                    disableEdit
                    description="A label for classifying income transactions."
                />
                <CrudResource
                    title="Expense categories"
                    singular="expense category"
                    baseUrl="/categories"
                    items={categories.filter((c) => c.type === 'expense')}
                    columns={nameColumn}
                    fields={fields}
                    fixedValues={{ type: 'expense' }}
                    onRowClick={(item) =>
                        router.visit(`/categories/${item.id}`)
                    }
                    disableEdit
                    description="A label for classifying expense transactions."
                />
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: '/categories' }],
};
