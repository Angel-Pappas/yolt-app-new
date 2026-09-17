import { Head, router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { includesNormalized } from '@/lib/search';
import { CategoryFormDialog } from './category-form-dialog';

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
    const [search, setSearch] = useState('');
    const [addOpen, setAddOpen] = useState(false);
    // Bumped on every open so the reused dialog remounts with a fresh form.
    const [addKey, setAddKey] = useState(0);

    function openAdd() {
        setAddKey((k) => k + 1);
        setAddOpen(true);
    }

    function destroy(category: Category) {
        if (confirm(`Delete category "${category.name}"?`)) {
            router.delete(`/configuration/categories/${category.id}`, {
                preserveScroll: true,
            });
        }
    }

    // One search box filters both lists (by name or description).
    const matches = (c: Category) =>
        includesNormalized(`${c.name} ${c.description ?? ''}`, search);
    const income = categories.filter((c) => c.type === 'income' && matches(c));
    const expense = categories.filter(
        (c) => c.type === 'expense' && matches(c),
    );

    // No per-column filters here — the shared search above covers both lists.
    const columns: ColumnDef<Category>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <ColumnHeader column={column} title="Name" />
            ),
            cell: ({ row }) => (
                <div>
                    <div>{row.original.name}</div>
                    {row.original.description ? (
                        <div className="text-muted-foreground text-sm">
                            {row.original.description}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'actions',
            enableSorting: false,
            meta: { align: 'right' },
            header: () => null,
            cell: ({ row }) => (
                <div
                    className="flex justify-end"
                    onClick={(e) => e.stopPropagation()}
                >
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => destroy(row.original)}
                        aria-label={`Delete ${row.original.name}`}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    const openCategory = (item: Category) =>
        router.visit(`/configuration/categories/${item.id}`);

    return (
        <>
            <Head title="Categories" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-2xl font-semibold">Categories</h1>
                    <div className="ml-auto flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search categories…"
                            className="w-56"
                            aria-label="Search categories"
                        />
                        <Button
                            onClick={openAdd}
                            size="icon"
                            aria-label="Add category"
                            title="Add category"
                        >
                            <Plus className="size-4" />
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <h2 className="text-lg font-semibold">
                            Income categories
                        </h2>
                        <DataTable
                            columns={columns}
                            data={income}
                            emptyMessage="No income categories yet."
                            onRowClick={openCategory}
                            pageSize={1000}
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <h2 className="text-lg font-semibold">
                            Expense categories
                        </h2>
                        <DataTable
                            columns={columns}
                            data={expense}
                            emptyMessage="No expense categories yet."
                            onRowClick={openCategory}
                            pageSize={1000}
                        />
                    </div>
                </div>

                <CategoryFormDialog
                    key={addKey}
                    open={addOpen}
                    onOpenChange={setAddOpen}
                    initialType="income"
                />
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: '/configuration/categories' }],
};
