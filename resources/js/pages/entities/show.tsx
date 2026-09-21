import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { TransactionsTable } from '@/components/transactions/transactions-table';
import { type Transaction } from '@/components/transactions/types';
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
import { type RecurrenceRecord } from './recurrence-form-dialog';
import { RecurrencesPanel } from './recurrences-panel';

type Entity = {
    id: number;
    name: string;
    type: string | null;
    vat_number: string | null;
};

type Props = {
    entity: Entity;
    transactions: Transaction[];
    recurrences: RecurrenceRecord[];
    listSlug: string;
    listTitle: string;
};

// The unclassified sentinel — Radix Select can't hold an empty value, so "Cheese"
// rides as `none` and is mapped back to '' (→ null type) on submit.
const NONE = 'none';

const TYPE_OPTIONS = [
    { value: NONE, label: 'Unclassified (Cheese)' },
    { value: 'customer', label: 'Customer' },
    { value: 'supplier', label: 'Supplier' },
    { value: 'contractor', label: 'Contractor' },
    { value: 'employee', label: 'Employee' },
    { value: 'state', label: 'State' },
];

export default function EntityShow({
    entity,
    transactions,
    recurrences,
    listSlug,
    listTitle,
}: Props) {
    const form = useForm({
        name: entity.name,
        type: entity.type ?? NONE,
        vat_number: entity.vat_number ?? '',
    });

    function save(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            type: data.type === NONE ? '' : data.type,
        }));
        form.patch(`/entities/${entity.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title={entity.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link
                            href={`/entities/${listSlug}`}
                            aria-label={`Back to ${listTitle}`}
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold">{entity.name}</h1>
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
                                <Label htmlFor="type">Type</Label>
                                <Select
                                    value={form.data.type}
                                    onValueChange={(v) =>
                                        form.setData('type', v)
                                    }
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {TYPE_OPTIONS.map((o) => (
                                            <SelectItem
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.type} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="vat_number">VAT number</Label>
                                <Input
                                    id="vat_number"
                                    value={form.data.vat_number}
                                    onChange={(e) =>
                                        form.setData(
                                            'vat_number',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Optional"
                                />
                                <InputError message={form.errors.vat_number} />
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

                <RecurrencesPanel
                    entityId={entity.id}
                    entityType={entity.type}
                    recurrences={recurrences}
                />

                <TransactionsTable
                    transactions={transactions}
                    title="Transactions"
                    searchPlaceholder="Search transactions…"
                    emptyMessage="No transactions with this entity yet."
                />
            </div>
        </>
    );
}

EntityShow.layout = {
    breadcrumbs: [{ title: 'Entities', href: '/entities/customers' }],
};
