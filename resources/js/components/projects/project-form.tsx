import { type Page } from '@inertiajs/core';
import { type InertiaFormProps } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AmountInput } from '@/components/amount-input';
import { CrudFormDialog } from '@/components/crud/crud-form-dialog';
import InputError from '@/components/input-error';
import {
    type FinanceLookups,
    useFinanceLookups,
} from '@/components/transactions/lookups';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { normalizeAmount, parseAmount } from '@/lib/amounts';
import { formatAmount } from '@/lib/format';

export type Deliverable = { name: string; net: string };

export type ProjectFormData = {
    name: string;
    entity_id: string;
    notes: string;
    deliverables: Deliverable[];
};

export type ProjectRow = {
    id: number;
    name: string;
    notes: string | null;
    entity_id: number;
    entity: { id: number; name: string };
    value: number;
    deliverables: { id: number; name: string; net: number }[];
};

export function projectFormData(project?: ProjectRow | null): ProjectFormData {
    return project
        ? {
              name: project.name,
              entity_id: String(project.entity_id),
              notes: project.notes ?? '',
              deliverables: project.deliverables.map((d) => ({
                  name: d.name,
                  net: String(d.net),
              })),
          }
        : {
              name: '',
              entity_id: '',
              notes: '',
              deliverables: [{ name: '', net: '' }],
          };
}

/** What the server receives: amounts as plain numbers. */
export function projectPayload(data: ProjectFormData) {
    return {
        ...data,
        deliverables: data.deliverables.map((d) => ({
            name: d.name,
            net: normalizeAmount(d.net),
        })),
    };
}

/**
 * A project's fields: name, customer (a "+" makes a new one in place — a
 * placeholder customer when the real one isn't known yet), notes, and its
 * deliverables (name + net, one or more), which add up to the project's value.
 */
export function ProjectFields({
    form,
}: {
    form: InertiaFormProps<ProjectFormData>;
}) {
    const { entities } = useFinanceLookups();
    const customers = entities.filter((e) => e.type === 'customer');
    const [addOpen, setAddOpen] = useState(false);
    const [addKey, setAddKey] = useState(0);
    const errors = form.errors as Record<string, string | undefined>;
    const total = form.data.deliverables.reduce(
        (sum, d) => sum + (parseAmount(d.net) || 0),
        0,
    );

    function setDeliverable(i: number, patch: Partial<Deliverable>) {
        form.setData(
            'deliverables',
            form.data.deliverables.map((d, j) =>
                j === i ? { ...d, ...patch } : d,
            ),
        );
    }

    function selectNewCustomer(page: Page) {
        const list =
            (page.props as { financeLookups?: FinanceLookups }).financeLookups
                ?.entities ?? [];
        const added = list.find((e) => !entities.some((o) => o.id === e.id));
        if (added) form.setData('entity_id', String(added.id));
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="project-name">Name</Label>
                <Input
                    id="project-name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
                <InputError message={form.errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="project-customer">Customer</Label>
                <div className="flex gap-2">
                    <div className="min-w-0 flex-1">
                        <Combobox
                            id="project-customer"
                            value={form.data.entity_id}
                            onChange={(v) => form.setData('entity_id', v)}
                            options={customers.map((c) => ({
                                value: String(c.id),
                                label: c.name,
                            }))}
                            placeholder="Choose a customer"
                            searchPlaceholder="Search customers…"
                            emptyText="No customers found."
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="shrink-0"
                        onClick={() => {
                            setAddKey((k) => k + 1);
                            setAddOpen(true);
                        }}
                        aria-label="Add customer"
                        title="Add customer (a placeholder name is fine while the real one isn't known)"
                    >
                        <Plus className="size-4" />
                    </Button>
                </div>
                <InputError message={form.errors.entity_id} />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <div className="flex items-center justify-between">
                    <Label>Deliverables</Label>
                    <span className="text-muted-foreground text-sm tabular-nums">
                        Value {formatAmount(total)}
                    </span>
                </div>
                {form.data.deliverables.map((d, i) => (
                    <div key={i} className="flex gap-2">
                        <Input
                            aria-label={`Deliverable ${i + 1}`}
                            placeholder="What is delivered"
                            value={d.name}
                            onChange={(e) =>
                                setDeliverable(i, { name: e.target.value })
                            }
                            className="min-w-0 flex-1"
                        />
                        <AmountInput
                            aria-label={`Deliverable ${i + 1} net`}
                            placeholder="Net"
                            value={d.net}
                            onChange={(v) => setDeliverable(i, { net: v })}
                            className="w-32"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            disabled={form.data.deliverables.length === 1}
                            onClick={() =>
                                form.setData(
                                    'deliverables',
                                    form.data.deliverables.filter(
                                        (_, j) => j !== i,
                                    ),
                                )
                            }
                            aria-label="Remove deliverable"
                            title="Remove deliverable"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ))}
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            form.setData('deliverables', [
                                ...form.data.deliverables,
                                { name: '', net: '' },
                            ])
                        }
                    >
                        <Plus className="size-4" /> Deliverable
                    </Button>
                </div>
                <InputError
                    message={
                        errors.deliverables ??
                        Object.entries(errors).find(([k]) =>
                            k.startsWith('deliverables.'),
                        )?.[1]
                    }
                />
            </div>

            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor="project-notes">Notes</Label>
                <Textarea
                    id="project-notes"
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                    rows={2}
                />
            </div>

            <CrudFormDialog
                key={`customer-${addKey}`}
                open={addOpen}
                onOpenChange={setAddOpen}
                singular="customer"
                baseUrl="/entities"
                fields={[
                    {
                        key: 'name',
                        label: 'Name',
                        type: 'text',
                        required: true,
                    },
                    {
                        key: 'vat_number',
                        label: 'VAT number (optional)',
                        type: 'text',
                    },
                ]}
                fixedValues={{ type: 'customer' }}
                only={['financeLookups']}
                onSaved={selectNewCustomer}
            />
        </div>
    );
}
