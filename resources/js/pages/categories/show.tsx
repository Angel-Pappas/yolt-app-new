import { Head, Link, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatAmount, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

type Related = { id: number; name: string } | null;
type TransactionType = 'income' | 'expense' | 'transfer';

type Transaction = {
    id: number;
    date: string;
    description: string;
    type: TransactionType;
    net: string;
    vat_amount: string;
    withheld_amount: string;
    wallet: Related;
    entity: Related;
};

type Category = {
    id: number;
    name: string;
    type: string;
    description: string | null;
};

type Option = { id: number; name: string };

type Props = {
    category: Category;
    transactions: Transaction[];
    targets: Option[];
};

const typeMeta: Record<TransactionType, { label: string; className: string }> =
    {
        income: {
            label: 'Income',
            className: 'text-green-600 dark:text-green-500',
        },
        expense: {
            label: 'Expense',
            className: 'text-red-600 dark:text-red-500',
        },
        transfer: { label: 'Transfer', className: 'text-muted-foreground' },
    };

function total(t: Transaction): number {
    return Number(t.net) + Number(t.vat_amount) - Number(t.withheld_amount);
}

export default function CategoryShow({
    category,
    transactions,
    targets,
}: Props) {
    const form = useForm({
        name: category.name,
        description: category.description ?? '',
        type: category.type,
    });
    const [moveTarget, setMoveTarget] = useState('');

    function save(e: FormEvent) {
        e.preventDefault();
        form.patch(`/categories/${category.id}`, { preserveScroll: true });
    }

    function moveSelected(ids: number[], clear: () => void) {
        if (!moveTarget) return;
        router.patch(
            '/transactions/bulk/category',
            { ids, category_id: Number(moveTarget) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clear();
                    setMoveTarget('');
                },
            },
        );
    }

    function deleteSelected(ids: number[], clear: () => void) {
        if (!confirm(`Delete ${ids.length} transaction(s)?`)) return;
        router.delete('/transactions/bulk', {
            data: { ids },
            preserveScroll: true,
            onSuccess: clear,
        });
    }

    const columns: ColumnDef<Transaction>[] = [
        {
            accessorKey: 'type',
            meta: {
                filter: {
                    type: 'select',
                    options: [
                        { value: 'income', label: 'Income' },
                        { value: 'expense', label: 'Expense' },
                    ],
                },
            },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Type" />
            ),
            cell: ({ row }) => (
                <span
                    className={cn(
                        'font-medium',
                        typeMeta[row.original.type].className,
                    )}
                >
                    {typeMeta[row.original.type].label}
                </span>
            ),
        },
        {
            accessorKey: 'date',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Date" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap tabular-nums">
                    {formatDate(row.original.date)}
                </span>
            ),
        },
        {
            id: 'wallet',
            accessorFn: (row) => row.wallet?.name ?? '',
            meta: { filter: { type: 'select' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Wallet" />
            ),
            cell: ({ row }) => row.original.wallet?.name ?? '—',
        },
        {
            id: 'entity',
            accessorFn: (row) => row.entity?.name ?? '',
            meta: { filter: { type: 'select' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Entity" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.entity?.name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'description',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Description" />
            ),
            cell: ({ row }) => row.original.description || '—',
        },
        {
            id: 'net',
            accessorFn: (row) => Number(row.net),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Net" align="right" />
            ),
            cell: ({ row }) => formatAmount(row.original.net),
        },
        {
            id: 'vat',
            accessorFn: (row) => Number(row.vat_amount),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="VAT" align="right" />
            ),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {formatAmount(row.original.vat_amount)}
                </span>
            ),
        },
        {
            id: 'total',
            accessorFn: (row) => total(row),
            meta: { align: 'right', filter: { type: 'number' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Total" align="right" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">
                    {formatAmount(total(row.original))}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={category.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link
                            href="/categories"
                            aria-label="Back to categories"
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {category.name}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {category.type === 'income'
                                ? 'Income category'
                                : 'Expense category'}
                        </p>
                    </div>
                </div>

                <Card>
                    <CardContent>
                        <form
                            onSubmit={save}
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    placeholder="Optional — what this category is for"
                                />
                                <InputError message={form.errors.description} />
                            </div>
                            <div className="sm:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <DataTable
                    columns={columns}
                    data={transactions}
                    title="Transactions"
                    searchPlaceholder="Search transactions…"
                    emptyMessage="No transactions in this category."
                    pageSize={50}
                    enableSelection
                    getRowId={(t) => String(t.id)}
                    renderBulkActions={(selected, clear) => {
                        const ids = selected.map((t) => t.id);
                        return (
                            <>
                                <Select
                                    value={moveTarget}
                                    onValueChange={setMoveTarget}
                                >
                                    <SelectTrigger
                                        className="h-8 w-56"
                                        aria-label="Move to category"
                                    >
                                        <SelectValue placeholder="Move to category…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {targets.map((c) => (
                                            <SelectItem
                                                key={c.id}
                                                value={String(c.id)}
                                            >
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button
                                    size="sm"
                                    disabled={!moveTarget}
                                    onClick={() => moveSelected(ids, clear)}
                                >
                                    Move
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => deleteSelected(ids, clear)}
                                >
                                    <Trash2 className="size-4" />
                                    Delete
                                </Button>
                            </>
                        );
                    }}
                />
            </div>
        </>
    );
}

CategoryShow.layout = {
    breadcrumbs: [{ title: 'Categories', href: '/categories' }],
};
