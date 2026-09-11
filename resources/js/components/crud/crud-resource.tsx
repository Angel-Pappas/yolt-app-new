import { router, useForm } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { type ColumnFilterMeta } from '@/components/data-table/column-filter';
import { ColumnHeader } from '@/components/data-table/column-header';
import { DataTable } from '@/components/data-table/data-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type CrudItem = { id: number } & Record<
    string,
    string | number | boolean | null
>;

export type CrudColumn = {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    render?: (item: CrudItem) => ReactNode;
    /** Header filter for this column (text/select/number/date). */
    filter?: ColumnFilterMeta;
};

export type CrudField = {
    key: string;
    label: string;
    type?: 'text' | 'decimal' | 'select';
    options?: { value: string; label: string }[];
    placeholder?: string;
    required?: boolean;
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
};

function blankData(
    fields: CrudField[],
    fixedValues: Record<string, string>,
): Record<string, string> {
    const data: Record<string, string> = { ...fixedValues };
    for (const field of fields) {
        data[field.key] =
            field.type === 'select' ? (field.options?.[0]?.value ?? '') : '';
    }
    return data;
}

/**
 * A generic list + add/edit dialog + soft-delete for the simple lookup/reference
 * resources (categories, VAT rates, etc.). Backed by RESTful routes at `baseUrl`
 * (POST create, PATCH `{id}`, DELETE `{id}`). Fields render as text, decimal
 * (comma or dot accepted, normalized to a dot), or a select.
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
}: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<CrudItem | null>(null);
    const form = useForm<Record<string, string>>(
        blankData(fields, fixedValues),
    );

    function openCreate() {
        setEditing(null);
        form.setData(blankData(fields, fixedValues));
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(item: CrudItem) {
        setEditing(item);
        const data: Record<string, string> = { ...fixedValues };
        for (const field of fields) {
            const value = item[field.key];
            data[field.key] =
                value === null || value === undefined ? '' : String(value);
        }
        form.setData(data);
        form.clearErrors();
        setOpen(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        form.transform((data) => {
            const out = { ...data };
            for (const field of fields) {
                if (field.type === 'decimal') {
                    out[field.key] = String(out[field.key]).replace(',', '.');
                }
            }
            return out;
        });

        const options = {
            onSuccess: () => setOpen(false),
            preserveScroll: true,
        };

        if (editing) {
            form.patch(`${baseUrl}/${editing.id}`, options);
        } else {
            form.post(baseUrl, options);
        }
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
                <div className="flex justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(row.original)}
                        aria-label={`Edit ${singular}`}
                    >
                        <Pencil className="size-4" />
                    </Button>
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

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? `Edit ${singular}`
                                    : `Add ${singular}`}
                            </DialogTitle>
                            {description && (
                                <DialogDescription>
                                    {description}
                                </DialogDescription>
                            )}
                        </DialogHeader>

                        <div className="grid gap-4 py-4">
                            {fields.map((field) => (
                                <div key={field.key} className="grid gap-2">
                                    <Label htmlFor={field.key}>
                                        {field.label}
                                    </Label>
                                    {field.type === 'select' ? (
                                        <Select
                                            value={form.data[field.key]}
                                            onValueChange={(v) =>
                                                form.setData(field.key, v)
                                            }
                                        >
                                            <SelectTrigger id={field.key}>
                                                <SelectValue
                                                    placeholder={
                                                        field.placeholder
                                                    }
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {field.options?.map((o) => (
                                                    <SelectItem
                                                        key={o.value}
                                                        value={o.value}
                                                    >
                                                        {o.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <Input
                                            id={field.key}
                                            inputMode={
                                                field.type === 'decimal'
                                                    ? 'decimal'
                                                    : undefined
                                            }
                                            value={form.data[field.key]}
                                            onChange={(e) =>
                                                form.setData(
                                                    field.key,
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={field.placeholder}
                                            required={field.required}
                                        />
                                    )}
                                    <InputError
                                        message={form.errors[field.key]}
                                    />
                                </div>
                            ))}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
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
        </>
    );
}
