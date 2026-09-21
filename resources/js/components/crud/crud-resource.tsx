import { router } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import {
    type CrudField,
    type CrudItem,
    CrudFormDialog,
} from '@/components/crud/crud-form-dialog';
import { type ColumnFilterMeta } from '@/components/data-table/column-filter';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import { Button } from '@/components/ui/button';

export type { CrudField, CrudItem };

export type CrudColumn = {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    render?: (item: CrudItem) => ReactNode;
    /** Header filter for this column (text/select/number/date). */
    filter?: ColumnFilterMeta;
};

type Props = {
    title: string;
    singular: string;
    baseUrl: string;
    items: CrudItem[];
    columns: CrudColumn[];
    fields: CrudField[];
    description?: string;
    /**
     * Values submitted on create/edit but not rendered as fields — e.g. a list
     * scoped to one `type` locks that column so every row it adds gets it.
     */
    fixedValues?: Record<string, string>;
    /** Called when a row is clicked (e.g. to open the item's own page). */
    onRowClick?: (item: CrudItem) => void;
    /**
     * Hide the per-row edit (pencil) button — for a resource that edits on its own
     * page instead of in the list's dialog. The Add dialog is unaffected.
     */
    disableEdit?: boolean;
    /**
     * Opt-in row selection + a bulk-actions bar (forwarded to {@see DataTable}).
     * `renderBulkActions` receives the selected items and a clear callback.
     */
    enableSelection?: boolean;
    getRowId?: (item: CrudItem) => string;
    renderBulkActions?: (selected: CrudItem[], clear: () => void) => ReactNode;
};

/**
 * A generic list + add/edit dialog + soft-delete for the simple lookup/reference
 * resources (categories, VAT rates, etc.). Backed by RESTful routes at `baseUrl`
 * (POST create, PATCH `{id}`, DELETE `{id}`). The add/edit form is the shared
 * {@see CrudFormDialog}, remounted via `key` on each open for a fresh form.
 */
export function CrudResource({
    title,
    singular,
    baseUrl,
    items,
    columns,
    fields,
    description,
    fixedValues = {},
    onRowClick,
    disableEdit = false,
    enableSelection = false,
    getRowId,
    renderBulkActions,
}: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<CrudItem | null>(null);
    // Bumped on every open so the reused dialog remounts with a fresh form.
    const [formKey, setFormKey] = useState(0);

    function openCreate() {
        setEditing(null);
        setFormKey((k) => k + 1);
        setOpen(true);
    }

    function openEdit(item: CrudItem) {
        setEditing(item);
        setFormKey((k) => k + 1);
        setOpen(true);
    }

    function destroy(item: CrudItem) {
        if (confirm(`Delete this ${singular}?`)) {
            router.delete(`${baseUrl}/${item.id}`, { preserveScroll: true });
        }
    }

    const tableColumns: ColumnDef<CrudItem>[] = [
        ...columns.map((col): ColumnDef<CrudItem> => ({
            accessorKey: col.key,
            meta: { align: col.align, filter: col.filter },
            header: ({ column }) => (
                <ColumnHeader
                    column={column}
                    title={col.label}
                    align={col.align}
                />
            ),
            cell: ({ row }) =>
                col.render
                    ? col.render(row.original)
                    : String(row.original[col.key] ?? '—'),
        })),
        {
            id: 'actions',
            enableSorting: false,
            enableGlobalFilter: false,
            meta: { align: 'right' },
            header: () => null,
            cell: ({ row }) => (
                <div
                    className="flex justify-end gap-1"
                    onClick={(e) => e.stopPropagation()}
                >
                    {!disableEdit && (
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => openEdit(row.original)}
                            aria-label={`Edit ${singular}`}
                        >
                            <Pencil className="size-4" />
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => destroy(row.original)}
                        aria-label={`Delete ${singular}`}
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
                columns={tableColumns}
                data={items}
                title={title}
                searchPlaceholder={`Search ${title.toLowerCase()}…`}
                emptyMessage={`No ${title.toLowerCase()} yet.`}
                onRowClick={onRowClick}
                enableSelection={enableSelection}
                getRowId={getRowId}
                renderBulkActions={renderBulkActions}
                action={
                    <Button
                        onClick={openCreate}
                        size="icon"
                        aria-label={`Add ${singular}`}
                        title={`Add ${singular}`}
                    >
                        <Plus className="size-4" />
                    </Button>
                }
            />

            <CrudFormDialog
                key={formKey}
                open={open}
                onOpenChange={setOpen}
                singular={singular}
                baseUrl={baseUrl}
                fields={fields}
                editing={editing}
                fixedValues={fixedValues}
                description={description}
            />
        </>
    );
}
