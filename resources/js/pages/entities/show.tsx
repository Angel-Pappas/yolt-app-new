import { Head, Link, router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { ArrowLeft } from 'lucide-react';
import { type FormEvent } from 'react';
import {
    type Agreement,
    AgreementsTable,
} from '@/components/agreements/agreements-table';
import InputError from '@/components/input-error';
import { SubTabs } from '@/components/page-tabs';
import { PaymentsTable } from '@/components/payments/payments-table';
import { type PaymentRow } from '@/components/payments/types';
import { type ProjectRow } from '@/components/projects/project-form';
import { DataTable } from '@/components/data-table/data-table';
import { ColumnHeader } from '@/components/data-table/column-header';
import { useStoredState } from '@/hooks/use-stored-state';
import { formatAmount } from '@/lib/format';
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
import { type RecurrenceRecord } from '@/components/recurrences/recurrence-form-dialog';
import { type EmployeeDetails, EmployeePanel } from './employee-panel';
import { type LeaveData, LeavePanel } from './leave-panel';
import { RecurrencesPanel } from './recurrences-panel';
import {
    RecurringIncomePanel,
    type RecurringIncomeRow,
} from './recurring-income-panel';
import { TerminationPanel } from './termination-panel';

type Entity = {
    id: number;
    name: string;
    type: string;
    vat_number: string | null;
};

type Props = {
    entity: Entity;
    transactions: Transaction[];
    recurrences: RecurrenceRecord[];
    agreements: Agreement[];
    /** An employee's details for payroll, leave and severance; null otherwise. */
    employee: EmployeeDetails | null;
    /** An employee's leave entries and yearly summary; null otherwise. */
    leave: LeaveData | null;
    /** A customer's projects, payment lines and recurring income; null otherwise. */
    customer: CustomerData | null;
    /** The back link: the entity's own tab of Entities. */
    listHref: string;
    listTitle: string;
};

type CustomerData = {
    projects: ProjectRow[];
    payments: PaymentRow[];
    recurring: RecurringIncomeRow[];
    /** Net value of its projects, and the net its payment lines plan. */
    value: number;
    planned: number;
};

type CustomerTab =
    | 'payments'
    | 'projects'
    | 'recurring'
    | 'agreements'
    | 'transactions';

const CUSTOMER_TABS: { key: CustomerTab; label: string }[] = [
    { key: 'payments', label: 'Payments' },
    { key: 'projects', label: 'Projects' },
    { key: 'recurring', label: 'Recurring income' },
    { key: 'agreements', label: 'Agreements' },
    { key: 'transactions', label: 'Transactions' },
];

const projectColumns: ColumnDef<ProjectRow>[] = [
    {
        id: 'name',
        accessorFn: (p) => p.name,
        header: ({ column }) => (
            <ColumnHeader column={column} title="Project" />
        ),
        cell: ({ row }) => (
            <span className="font-medium">{row.original.name}</span>
        ),
    },
    {
        id: 'deliverables',
        accessorFn: (p) => p.deliverables.map((d) => d.name).join(', '),
        header: ({ column }) => (
            <ColumnHeader column={column} title="Deliverables" />
        ),
    },
    {
        id: 'value',
        accessorFn: (p) => p.value,
        meta: { align: 'right' },
        header: ({ column }) => (
            <ColumnHeader column={column} title="Value" align="right" />
        ),
        cell: ({ row }) => formatAmount(row.original.value),
    },
];

const TYPE_OPTIONS = [
    { value: 'customer', label: 'Customer' },
    { value: 'supplier', label: 'Supplier' },
    { value: 'contractor', label: 'Contractor' },
    { value: 'employee', label: 'Employee' },
    { value: 'shareholder', label: 'Shareholder' },
    { value: 'state', label: 'State' },
];

export default function EntityShow({
    entity,
    transactions,
    recurrences,
    agreements,
    employee,
    leave,
    customer,
    listHref,
    listTitle,
}: Props) {
    // A deep link (`?tab=payments`) opens that tab; else the one used last.
    const linked = new URLSearchParams(
        typeof window === 'undefined' ? '' : window.location.search,
    ).get('tab') as CustomerTab | null;
    const [tab, setTab] = useStoredState<CustomerTab>(
        'customer-tab',
        'payments',
        CUSTOMER_TABS.some((t) => t.key === linked)
            ? (linked as CustomerTab)
            : undefined,
    );
    const form = useForm({
        name: entity.name,
        type: entity.type,
        vat_number: entity.vat_number ?? '',
    });

    const customerTabs = (
        <SubTabs
            label="Customer"
            tabs={CUSTOMER_TABS}
            active={tab}
            onChange={setTab}
        />
    );

    function save(e: FormEvent) {
        e.preventDefault();
        form.patch(`/entities/${entity.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title={entity.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link
                            href={listHref}
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

                {employee && (
                    <EmployeePanel entityId={entity.id} employee={employee} />
                )}

                {customer ? (
                    <>
                        {tab === 'payments' && (
                            <PaymentsTable
                                rows={customer.payments}
                                entityId={entity.id}
                                heading={customerTabs}
                                stateKey={`customer-${entity.id}-payments`}
                                controls={
                                    <p className="text-muted-foreground text-sm tabular-nums">
                                        Planned {formatAmount(customer.planned)}{' '}
                                        of {formatAmount(customer.value)} net
                                        (its projects)
                                    </p>
                                }
                            />
                        )}
                        {tab === 'projects' && (
                            <DataTable
                                columns={projectColumns}
                                data={customer.projects}
                                title="Projects"
                                heading={customerTabs}
                                emptyMessage="No projects for this customer."
                                onRowClick={(p) =>
                                    router.visit(`/projects/${p.id}`)
                                }
                                rowHref={(p) => `/projects/${p.id}`}
                            />
                        )}
                        {tab === 'recurring' && (
                            <RecurringIncomePanel
                                heading={customerTabs}
                                entityId={entity.id}
                                rows={customer.recurring}
                            />
                        )}
                        {tab === 'agreements' && (
                            <AgreementsTable
                                agreements={agreements}
                                entityId={entity.id}
                                heading={customerTabs}
                            />
                        )}
                        {tab === 'transactions' && (
                            <TransactionsTable
                                transactions={transactions}
                                heading={customerTabs}
                                title="Transactions"
                                searchPlaceholder="Search transactions…"
                                emptyMessage="No transactions with this entity yet."
                            />
                        )}
                    </>
                ) : (
                    <>
                        <RecurrencesPanel
                            entity={entity}
                            recurrences={recurrences}
                        />

                        {employee && <TerminationPanel entityId={entity.id} />}
                        {leave && (
                            <LeavePanel entityId={entity.id} leave={leave} />
                        )}

                        {entity.type !== 'state' && (
                            <AgreementsTable
                                agreements={agreements}
                                entityId={entity.id}
                            />
                        )}

                        <TransactionsTable
                            transactions={transactions}
                            title="Transactions"
                            searchPlaceholder="Search transactions…"
                            emptyMessage="No transactions with this entity yet."
                        />
                    </>
                )}
            </div>
        </>
    );
}

EntityShow.layout = {
    breadcrumbs: [{ title: 'Entities', href: '/entities/customers' }],
};
