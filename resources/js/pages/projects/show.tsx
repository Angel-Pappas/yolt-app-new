import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { AmountInput } from '@/components/amount-input';
import { ExcludeToggle } from '@/components/exclude-toggle';
import InputError from '@/components/input-error';
import { useFinanceLookups } from '@/components/transactions/lookups';
import { TransactionsTable } from '@/components/transactions/transactions-table';
import { type Transaction } from '@/components/transactions/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { normalizeAmount } from '@/lib/amounts';
import { formatAmount, formatDate } from '@/lib/format';
import { TransactionFormDialog } from '@/pages/transactions/transaction-form-dialog';

type Project = {
    id: number;
    name: string;
    entity_id: number;
    agreed_total: string | null;
    notes: string | null;
    is_excluded: boolean;
    entity: { id: number; name: string } | null;
};

type Summary = {
    installments: number;
    scheduled: number;
    invoiced: number;
    paid: number;
    outstanding: number;
    unscheduled: number | null;
    next_due: string | null;
    done: boolean;
};

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground text-xs">{label}</div>
            <div className="font-medium tabular-nums">{value}</div>
        </div>
    );
}

/**
 * A project's own page: its details, the money summary and its installments — the
 * income transactions that belong to it, in the shared transactions table (status
 * tag, reconcile, invoice month, on/off switch). "+" adds an installment with the
 * shared transaction form, already tied to this project and its customer.
 */
export default function ProjectShow({
    project,
    summary,
    transactions,
}: {
    project: Project;
    summary: Summary;
    transactions: Transaction[];
}) {
    const { entities, wallets } = useFinanceLookups();
    const form = useForm({
        name: project.name,
        entity_id: String(project.entity_id),
        agreed_total: project.agreed_total ?? '',
        notes: project.notes ?? '',
    });
    const [addOpen, setAddOpen] = useState(false);
    const [addKey, setAddKey] = useState(0);

    function save(e: FormEvent) {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            agreed_total:
                data.agreed_total === ''
                    ? null
                    : normalizeAmount(data.agreed_total),
        }));
        form.patch(`/projects/${project.id}`, { preserveScroll: true });
    }

    function destroy() {
        if (
            confirm(
                'Delete this project? Its installments stay as ordinary transactions.',
            )
        ) {
            router.delete(`/projects/${project.id}`);
        }
    }

    return (
        <>
            <Head title={project.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/projects" aria-label="Back to Projects">
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold">{project.name}</h1>
                    {project.is_excluded && (
                        <Badge variant="secondary">Excluded</Badge>
                    )}
                    <div className="ml-auto flex items-center gap-1">
                        <ExcludeToggle
                            url={`/projects/${project.id}/excluded`}
                            excluded={project.is_excluded}
                            label="project"
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={destroy}
                            aria-label="Delete project"
                            title="Delete project"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent className="grid gap-6">
                        <form
                            onSubmit={save}
                            className="grid gap-4 sm:grid-cols-3"
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
                                <Label htmlFor="entity_id">Customer</Label>
                                <Combobox
                                    id="entity_id"
                                    value={form.data.entity_id}
                                    onChange={(v) =>
                                        form.setData('entity_id', v)
                                    }
                                    options={entities
                                        .filter(
                                            (e) =>
                                                e.type === 'customer' ||
                                                String(e.id) ===
                                                    form.data.entity_id,
                                        )
                                        .map((e) => ({
                                            value: String(e.id),
                                            label: e.name,
                                        }))}
                                    searchPlaceholder="Search customers…"
                                    emptyText="No customers found."
                                />
                                <InputError message={form.errors.entity_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="agreed_total">
                                    Agreed total, net
                                </Label>
                                <AmountInput
                                    id="agreed_total"
                                    value={form.data.agreed_total}
                                    onChange={(v) =>
                                        form.setData('agreed_total', v)
                                    }
                                    placeholder="Optional"
                                />
                                <InputError
                                    message={form.errors.agreed_total}
                                />
                            </div>
                            <div className="grid gap-2 sm:col-span-3">
                                <Label htmlFor="notes">Notes</Label>
                                <Textarea
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    rows={2}
                                    placeholder="Optional"
                                />
                                <InputError message={form.errors.notes} />
                            </div>
                            <div className="sm:col-span-3">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save
                                </Button>
                            </div>
                        </form>

                        <div className="grid grid-cols-2 gap-4 border-t pt-4 sm:grid-cols-3 lg:grid-cols-6">
                            <Stat
                                label="Scheduled"
                                value={formatAmount(summary.scheduled)}
                            />
                            {summary.unscheduled != null && (
                                <Stat
                                    label="Not yet scheduled"
                                    value={formatAmount(summary.unscheduled)}
                                />
                            )}
                            <Stat
                                label="Invoiced"
                                value={formatAmount(summary.invoiced)}
                            />
                            <Stat
                                label="Paid"
                                value={formatAmount(summary.paid)}
                            />
                            <Stat
                                label="Outstanding"
                                value={formatAmount(summary.outstanding)}
                            />
                            <Stat
                                label="Next due"
                                value={
                                    summary.next_due
                                        ? formatDate(summary.next_due)
                                        : '—'
                                }
                            />
                        </div>
                    </CardContent>
                </Card>

                <TransactionsTable
                    transactions={transactions}
                    showStatus
                    hideColumns={['type', 'entity']}
                    rowActions={{
                        reconcile: true,
                        invoice: true,
                        delete: true,
                        exclude: true,
                    }}
                    title="Installments"
                    searchPlaceholder="Search installments…"
                    emptyMessage="No installments yet — add the payments agreed for this project."
                    action={
                        <Button
                            onClick={() => {
                                setAddKey((k) => k + 1);
                                setAddOpen(true);
                            }}
                            disabled={wallets.length === 0}
                            size="icon"
                            aria-label="Add installment"
                            title="Add installment"
                        >
                            <Plus className="size-4" />
                        </Button>
                    }
                />
            </div>

            <TransactionFormDialog
                key={`add-${addKey}`}
                open={addOpen}
                onOpenChange={setAddOpen}
                editing={null}
                project={{ id: project.id, entity_id: project.entity_id }}
            />
        </>
    );
}

ProjectShow.layout = {
    breadcrumbs: [{ title: 'Projects', href: '/projects' }],
};
