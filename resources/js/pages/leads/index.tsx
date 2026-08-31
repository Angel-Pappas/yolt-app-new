import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { type EditableLead, LeadFormDialog } from './lead-form-dialog';
import { type LeadFilters, LeadsFilters } from './leads-filters';

type Related = { id: number; name: string } | null;

type Lead = EditableLead & {
    sort_order: number;
    origin: Related;
    status: Related;
};

type Option = { id: number; name: string };

type Props = {
    leads: Lead[];
    filters: LeadFilters;
    statuses: Option[];
    origins: Option[];
};

export default function LeadsIndex({
    leads,
    filters,
    statuses,
    origins,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<EditableLead | null>(null);
    const [formKey, setFormKey] = useState(0);

    function openCreate() {
        setEditing(null);
        setFormKey((k) => k + 1);
        setDialogOpen(true);
    }

    function openEdit(lead: Lead) {
        setEditing(lead);
        setFormKey((k) => k + 1);
        setDialogOpen(true);
    }

    function destroy(lead: Lead) {
        if (confirm('Delete this lead?')) {
            router.delete(`/leads/${lead.id}`, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Leads" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Leads</h1>
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        Add lead
                    </Button>
                </div>

                <LeadsFilters
                    filters={filters}
                    statuses={statuses}
                    origins={origins}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-left">
                                <th className="p-3 font-medium">No.</th>
                                <th className="p-3 font-medium">Origin</th>
                                <th className="p-3 font-medium">Name</th>
                                <th className="p-3 font-medium">Email</th>
                                <th className="p-3 font-medium">Phone</th>
                                <th className="p-3 font-medium">Next step</th>
                                <th className="p-3 font-medium">Status</th>
                                <th className="p-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {leads.map((lead) => (
                                <tr key={lead.id} className="border-t">
                                    <td className="text-muted-foreground p-3 tabular-nums">
                                        {lead.sort_order}
                                    </td>
                                    <td className="text-muted-foreground p-3 whitespace-nowrap">
                                        {lead.origin?.name ?? '—'}
                                    </td>
                                    <td className="p-3 font-medium">
                                        <Link
                                            href={`/leads/${lead.id}`}
                                            className="hover:underline"
                                        >
                                            {lead.name}
                                        </Link>
                                    </td>
                                    <td className="text-muted-foreground p-3">
                                        {lead.contact_email || '—'}
                                    </td>
                                    <td className="text-muted-foreground p-3 whitespace-nowrap">
                                        {lead.contact_phone || '—'}
                                    </td>
                                    <td className="text-muted-foreground max-w-xs p-3">
                                        {lead.next_step || '—'}
                                    </td>
                                    <td className="p-3 whitespace-nowrap">
                                        {lead.status?.name ?? '—'}
                                    </td>
                                    <td className="p-3">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => openEdit(lead)}
                                                aria-label="Edit lead"
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => destroy(lead)}
                                                aria-label="Delete lead"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {leads.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="text-muted-foreground p-6 text-center"
                                    >
                                        No leads yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <LeadFormDialog
                key={formKey}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                editing={editing}
                statuses={statuses}
                origins={origins}
            />
        </>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'Leads', href: '/leads' }],
};
