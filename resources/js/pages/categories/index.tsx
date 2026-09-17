import { Head, router } from '@inertiajs/react';
import { type CrudColumn, CrudResource } from '@/components/crud/crud-resource';
import { categoryFields } from './category-fields';

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
    return (
        <>
            <Head title="Categories" />
            <div className="grid h-full flex-1 gap-6 p-4 lg:grid-cols-2">
                <CrudResource
                    title="Income categories"
                    singular="income category"
                    baseUrl="/configuration/categories"
                    items={categories.filter((c) => c.type === 'income')}
                    columns={nameColumn}
                    fields={categoryFields}
                    fixedValues={{ type: 'income' }}
                    onRowClick={(item) =>
                        router.visit(`/configuration/categories/${item.id}`)
                    }
                    disableEdit
                    description="A label for classifying income transactions."
                />
                <CrudResource
                    title="Expense categories"
                    singular="expense category"
                    baseUrl="/configuration/categories"
                    items={categories.filter((c) => c.type === 'expense')}
                    columns={nameColumn}
                    fields={categoryFields}
                    fixedValues={{ type: 'expense' }}
                    onRowClick={(item) =>
                        router.visit(`/configuration/categories/${item.id}`)
                    }
                    disableEdit
                    description="A label for classifying expense transactions."
                />
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: '/configuration/categories' }],
};
