import { router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { FileText, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable, DONE_ROW_CLASS } from '@/components/data-table/data-table';
import InputError from '@/components/input-error';
import { useFinanceLookups } from '@/components/transactions/lookups';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { DateField } from '@/components/ui/date-field';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';

/** One agreement, as `AgreementController::rows()` sends it. */
export type Agreement = {
    id: number;
    title: string;
    entity_id: number;
    entity: { id: number; name: string };
    start_date: string;
    end_date: string | null;
    notes: string | null;
    file_name: string | null;
    state: 'upcoming' | 'active' | 'ending' | 'ended';
    /** Days until it ends (negative once ended); null when open-ended. */
    days_left: number | null;
};

function StateBadge({ agreement }: { agreement: Agreement }) {
    switch (agreement.state) {
        case 'ending':
            return (
                <Badge className="border-transparent bg-amber-500 text-white">
                    {agreement.days_left === 0
                        ? 'Ends today'
                        : `Ends in ${agreement.days_left} day${agreement.days_left === 1 ? '' : 's'}`}
                </Badge>
            );
        case 'ended':
            return <Badge variant="secondary">Ended</Badge>;
        case 'upcoming':
            return <Badge variant="outline">Not started</Badge>;
        default:
            return <Badge variant="outline">Active</Badge>;
    }
}

function AgreementFormDialog({
    open,
    onOpenChange,
    editing,
    entityId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: Agreement | null;
    /** Fixed entity (on an entity's page); null shows a picker. */
    entityId: number | null;
}) {
    const { entities } = useFinanceLookups();
    const form = useForm<{
        entity_id: string;
        title: string;
        start_date: string;
        end_date: string;
        notes: string;
        file: File | null;
    }>({
        entity_id: editing
            ? String(editing.entity_id)
            : entityId
              ? String(entityId)
              : '',
        title: editing?.title ?? '',
        start_date:
            editing?.start_date ?? new Date().toISOString().slice(0, 10),
        end_date: editing?.end_date ?? '',
        notes: editing?.notes ?? '',
        file: null,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        // Always a POST: a file can only travel as multipart form data.
        form.post(editing ? `/agreements/${editing.id}` : '/agreements', {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit agreement' : 'Add agreement'}
                        </DialogTitle>
                        <DialogDescription>
                            The signed file and its dates, for the record. Leave
                            the end empty for an open-ended agreement.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4 sm:grid-cols-2">
                        {entityId === null && (
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="agreement_entity">With</Label>
                                <Combobox
                                    id="agreement_entity"
                                    value={form.data.entity_id}
                                    onChange={(v) =>
                                        form.setData('entity_id', v)
                                    }
                                    options={entities
                                        .filter((e) => e.type !== 'state')
                                        .map((e) => ({
                                            value: String(e.id),
                                            label: e.name,
                                        }))}
                                    placeholder="Choose an entity"
                                    searchPlaceholder="Search entities…"
                                    emptyText="No entities found."
                                />
                                <InputError message={form.errors.entity_id} />
                            </div>
                        )}
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="agreement_title">Title</Label>
                            <Input
                                id="agreement_title"
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="agreement_start">Starts</Label>
                            <DateField
                                id="agreement_start"
                                value={form.data.start_date}
                                showCalendar={false}
                                required
                                onChange={(iso) =>
                                    form.setData('start_date', iso)
                                }
                            />
                            <InputError message={form.errors.start_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="agreement_end">Ends</Label>
                            <DateField
                                id="agreement_end"
                                value={form.data.end_date}
                                showCalendar={false}
                                onChange={(iso) =>
                                    form.setData('end_date', iso)
                                }
                            />
                            <InputError message={form.errors.end_date} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="agreement_file">File</Label>
                            <Input
                                id="agreement_file"
                                type="file"
                                accept=".pdf,.doc,.docx,.odt,.xls,.xlsx,.jpg,.jpeg,.png,.txt"
                                onChange={(e) =>
                                    form.setData(
                                        'file',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            {editing?.file_name && (
                                <p className="text-muted-foreground text-xs">
                                    Now: {editing.file_name}. Choose a file only
                                    to replace it.
                                </p>
                            )}
                            <InputError message={form.errors.file} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="agreement_notes">Notes</Label>
                            <Textarea
                                id="agreement_notes"
                                value={form.data.notes}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                                rows={2}
                                placeholder="Optional"
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {editing ? 'Save' : 'Add'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The agreements table, used on the Agreements page (every entity) and on an
 * entity's own page (`entityId` set: its agreements only, no "With" column). A row
 * opens the edit form; the file icon opens the stored file in a new tab.
 */
export function AgreementsTable({
    agreements,
    entityId = null,
    title = 'Agreements',
    heading,
    controls,
    emptyMessage = 'No agreements yet.',
}: {
    agreements: Agreement[];
    entityId?: number | null;
    title?: string;
    /** Shown in the title's place (a page's tab bar). */
    heading?: ReactNode;
    controls?: ReactNode;
    emptyMessage?: string;
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Agreement | null>(null);
    const [formKey, setFormKey] = useState(0);

    function openForm(agreement: Agreement | null) {
        setEditing(agreement);
        setFormKey((k) => k + 1);
        setOpen(true);
    }

    const columns: ColumnDef<Agreement>[] = [
        {
            accessorKey: 'title',
            meta: { filter: { type: 'text' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Agreement" />
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.title}</span>
            ),
        },
        ...(entityId === null
            ? [
                  {
                      id: 'entity',
                      accessorFn: (row: Agreement) => row.entity.name,
                      meta: { filter: { type: 'text' as const } },
                      header: ({ column }) => (
                          <ColumnHeader column={column} title="With" />
                      ),
                      cell: ({ row }) => (
                          <span className="text-muted-foreground">
                              {row.original.entity.name}
                          </span>
                      ),
                  } as ColumnDef<Agreement>,
              ]
            : []),
        {
            accessorKey: 'start_date',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Starts" />
            ),
            cell: ({ row }) => formatDate(row.original.start_date),
        },
        {
            id: 'end_date',
            // Open-ended sorts after any date.
            accessorFn: (row) => row.end_date ?? '9999-12-31',
            meta: { filter: { type: 'date' } },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Ends" />
            ),
            cell: ({ row }) =>
                row.original.end_date ? (
                    formatDate(row.original.end_date)
                ) : (
                    <span className="text-muted-foreground">Open-ended</span>
                ),
        },
        {
            id: 'state',
            accessorFn: (row) => row.state,
            meta: {
                filter: {
                    type: 'select',
                    options: [
                        { value: 'active', label: 'Active' },
                        { value: 'ending', label: 'Ending soon' },
                        { value: 'upcoming', label: 'Not started' },
                        { value: 'ended', label: 'Ended' },
                    ],
                },
            },
            header: ({ column }) => (
                <ColumnHeader column={column} title="Status" />
            ),
            cell: ({ row }) => <StateBadge agreement={row.original} />,
        },
        {
            id: 'actions',
            enableSorting: false,
            meta: { align: 'right' },
            header: () => null,
            cell: ({ row }) => (
                <div
                    className="flex justify-end gap-1"
                    onClick={(e) => e.stopPropagation()}
                >
                    {row.original.file_name && (
                        <Button variant="ghost" size="icon" asChild>
                            <a
                                href={`/agreements/${row.original.id}/file`}
                                target="_blank"
                                rel="noreferrer"
                                title={`Open ${row.original.file_name}`}
                                aria-label={`Open ${row.original.file_name}`}
                            >
                                <FileText className="size-4" />
                            </a>
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Delete agreement"
                        onClick={() => {
                            if (confirm('Delete this agreement?')) {
                                router.delete(
                                    `/agreements/${row.original.id}`,
                                    { preserveScroll: true },
                                );
                            }
                        }}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <DataTable
                columns={columns}
                data={agreements}
                title={title}
                heading={heading}
                searchPlaceholder="Search agreements…"
                emptyMessage={emptyMessage}
                controls={controls}
                onRowClick={openForm}
                rowClassName={(a) =>
                    a.state === 'ended' ? DONE_ROW_CLASS : undefined
                }
                action={
                    <Button
                        onClick={() => openForm(null)}
                        size="icon"
                        aria-label="Add agreement"
                        title="Add agreement"
                    >
                        <Plus className="size-4" />
                    </Button>
                }
            />

            <AgreementFormDialog
                key={formKey}
                open={open}
                onOpenChange={setOpen}
                editing={editing}
                entityId={entityId}
            />
        </>
    );
}
