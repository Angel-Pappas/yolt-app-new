import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
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
};

function blankData(fields: CrudField[]): Record<string, string> {
    const data: Record<string, string> = {};
    for (const field of fields) {
        data[field.key] =
            field.type === 'select' ? (field.options?.[0]?.value ?? '') : '';
    }
    return data;
}

function alignClass(align?: string): string {
    if (align === 'right') return 'text-right';
    if (align === 'center') return 'text-center';
    return 'text-left';
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
}: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<CrudItem | null>(null);
    const form = useForm<Record<string, string>>(blankData(fields));

    function openCreate() {
        setEditing(null);
        form.setData(blankData(fields));
        form.clearErrors();
        setOpen(true);
    }

    function openEdit(item: CrudItem) {
        setEditing(item);
        const data: Record<string, string> = {};
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

    return (
        <>
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">{title}</h1>
                <Button onClick={openCreate}>
                    <Plus className="size-4" />
                    Add {singular}
                </Button>
            </div>

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted/50 text-left">
                            {columns.map((col) => (
                                <th
                                    key={col.key}
                                    className={`p-3 font-medium ${alignClass(col.align)}`}
                                >
                                    {col.label}
                                </th>
                            ))}
                            <th className="p-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr key={item.id} className="border-t">
                                {columns.map((col) => (
                                    <td
                                        key={col.key}
                                        className={`p-3 ${alignClass(col.align)}`}
                                    >
                                        {col.render
                                            ? col.render(item)
                                            : String(item[col.key] ?? '—')}
                                    </td>
                                ))}
                                <td className="p-3">
                                    <div className="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => openEdit(item)}
                                            aria-label={`Edit ${singular}`}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => destroy(item)}
                                            aria-label={`Delete ${singular}`}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {items.length === 0 && (
                            <tr>
                                <td
                                    colSpan={columns.length + 1}
                                    className="text-muted-foreground p-6 text-center"
                                >
                                    No {title.toLowerCase()} yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

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
