import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import {
    type EditableAction,
    ActionFormDialog,
} from '@/components/crm/action-form-dialog';
import { type EditableContact, ContactFormDialog } from './contact-form-dialog';
import { type EditableLead, LeadFormDialog } from './lead-form-dialog';

type Related = { id: number; name: string } | null;

type Lead = EditableLead & {
    sort_order: number;
    origin: Related;
    status: Related;
};

type Action = {
    id: number;
    action_date: string;
    body: string;
    author_name: string | null;
};

type Option = { id: number; name: string };

type Props = {
    lead: Lead;
    actions: Action[];
    contacts: EditableContact[];
    statuses: Option[];
    origins: Option[];
};

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <div className="text-muted-foreground text-xs">{label}</div>
            <div>{value || '—'}</div>
        </div>
    );
}

export default function LeadShow({
    lead,
    actions,
    contacts,
    statuses,
    origins,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [editKey, setEditKey] = useState(0);
    const [actionOpen, setActionOpen] = useState(false);
    const [editingAction, setEditingAction] = useState<EditableAction | null>(
        null,
    );
    const [actionKey, setActionKey] = useState(0);
    const [contactOpen, setContactOpen] = useState(false);
    const [editingContact, setEditingContact] =
        useState<EditableContact | null>(null);
    const [contactKey, setContactKey] = useState(0);

    function openAddAction() {
        setEditingAction(null);
        setActionKey((k) => k + 1);
        setActionOpen(true);
    }

    function openEditAction(action: Action) {
        setEditingAction(action);
        setActionKey((k) => k + 1);
        setActionOpen(true);
    }

    function destroyAction(action: Action) {
        if (confirm('Delete this action?')) {
            router.delete(`/leads/${lead.id}/actions/${action.id}`, {
                preserveScroll: true,
            });
        }
    }

    function openAddContact() {
        setEditingContact(null);
        setContactKey((k) => k + 1);
        setContactOpen(true);
    }

    function openEditContact(contact: EditableContact) {
        setEditingContact(contact);
        setContactKey((k) => k + 1);
        setContactOpen(true);
    }

    function destroyContact(contact: EditableContact) {
        if (confirm('Delete this contact?')) {
            router.delete(`/leads/${lead.id}/contacts/${contact.id}`, {
                preserveScroll: true,
            });
        }
    }

    return (
        <>
            <Head title={lead.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/leads" aria-label="Back to leads">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold">
                                {lead.name}
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                No. {lead.sort_order}
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => {
                            setEditKey((k) => k + 1);
                            setEditOpen(true);
                        }}
                    >
                        <Pencil className="size-4" />
                        Edit lead
                    </Button>
                </div>

                <Card>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        <Detail
                            label="Origin"
                            value={lead.origin?.name ?? null}
                        />
                        <Detail
                            label="Status"
                            value={lead.status?.name ?? null}
                        />
                        <Detail label="Website" value={lead.website} />
                        <Detail
                            label="Contact name"
                            value={lead.contact_name}
                        />
                        <Detail
                            label="Position"
                            value={lead.contact_position}
                        />
                        <Detail label="Email" value={lead.contact_email} />
                        <Detail label="Phone" value={lead.contact_phone} />
                        <Detail
                            label="Landline"
                            value={lead.contact_landline}
                        />
                        <div className="sm:col-span-3">
                            <Detail
                                label="Description"
                                value={lead.description}
                            />
                        </div>
                        <div className="sm:col-span-3">
                            <Detail label="Next step" value={lead.next_step} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle>History</CardTitle>
                        <Button size="sm" onClick={openAddAction}>
                            <Plus className="size-4" />
                            Log action
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground text-left">
                                        <th className="pb-2 font-medium">
                                            Date
                                        </th>
                                        <th className="pb-2 font-medium">
                                            User
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Action
                                        </th>
                                        <th className="pb-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {actions.map((action) => (
                                        <tr
                                            key={action.id}
                                            className="border-t"
                                        >
                                            <td className="py-2 whitespace-nowrap tabular-nums">
                                                {formatDate(action.action_date)}
                                            </td>
                                            <td className="text-muted-foreground py-2 whitespace-nowrap">
                                                {action.author_name ?? '—'}
                                            </td>
                                            <td className="py-2 whitespace-pre-wrap">
                                                {action.body}
                                            </td>
                                            <td className="py-2">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEditAction(
                                                                action,
                                                            )
                                                        }
                                                        aria-label="Edit action"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            destroyAction(
                                                                action,
                                                            )
                                                        }
                                                        aria-label="Delete action"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {actions.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="text-muted-foreground py-4 text-center"
                                            >
                                                No actions logged yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle>Contacts</CardTitle>
                        <Button size="sm" onClick={openAddContact}>
                            <Plus className="size-4" />
                            Add contact
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground text-left">
                                        <th className="pb-2 font-medium">
                                            Name
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Position
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Phone
                                        </th>
                                        <th className="pb-2 font-medium">
                                            Email
                                        </th>
                                        <th className="pb-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {contacts.map((contact) => (
                                        <tr
                                            key={contact.id}
                                            className="border-t"
                                        >
                                            <td className="py-2 font-medium">
                                                {contact.name}
                                            </td>
                                            <td className="text-muted-foreground py-2">
                                                {contact.position || '—'}
                                            </td>
                                            <td className="text-muted-foreground py-2 whitespace-nowrap">
                                                {contact.phone || '—'}
                                            </td>
                                            <td className="text-muted-foreground py-2">
                                                {contact.email || '—'}
                                            </td>
                                            <td className="py-2">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEditContact(
                                                                contact,
                                                            )
                                                        }
                                                        aria-label="Edit contact"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            destroyContact(
                                                                contact,
                                                            )
                                                        }
                                                        aria-label="Delete contact"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {contacts.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="text-muted-foreground py-4 text-center"
                                            >
                                                No additional contacts yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <LeadFormDialog
                key={editKey}
                open={editOpen}
                onOpenChange={setEditOpen}
                editing={lead}
                statuses={statuses}
                origins={origins}
            />

            <ActionFormDialog
                key={actionKey}
                open={actionOpen}
                onOpenChange={setActionOpen}
                baseUrl={`/leads/${lead.id}/actions`}
                editing={editingAction}
            />

            <ContactFormDialog
                key={contactKey}
                open={contactOpen}
                onOpenChange={setContactOpen}
                leadId={lead.id}
                editing={editingContact}
            />
        </>
    );
}

LeadShow.layout = {
    breadcrumbs: [{ title: 'Leads', href: '/leads' }],
};
