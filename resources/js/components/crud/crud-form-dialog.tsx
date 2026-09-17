import { type Page } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export type CrudItem = { id: number } & Record<
    string,
    string | number | boolean | null
>;

export type CrudField = {
    key: string;
    label: string;
    type?: 'text' | 'decimal' | 'select' | 'textarea' | 'date';
    options?: { value: string; label: string }[];
    placeholder?: string;
    required?: boolean;
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

function initialData(
    fields: CrudField[],
    fixedValues: Record<string, string>,
    editing: CrudItem | null,
): Record<string, string> {
    if (!editing) return blankData(fields, fixedValues);
    const data: Record<string, string> = { ...fixedValues };
    for (const field of fields) {
        const value = editing[field.key];
        data[field.key] =
            value === null || value === undefined ? '' : String(value);
    }
    return data;
}

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    singular: string;
    baseUrl: string;
    fields: CrudField[];
    editing?: CrudItem | null;
    /** Values submitted but not rendered as fields (e.g. a locked `type`). */
    fixedValues?: Record<string, string>;
    description?: string;
    /** Props to reload after a successful save (default: reload everything). */
    only?: string[];
    /** Called with the fresh page after a successful save (e.g. to select the new row). */
    onSaved?: (page: Page) => void;
};

/**
 * The shared add/edit dialog for the simple lookup/reference resources. It seeds its
 * form once from `editing`/`fixedValues`, so a caller remounts it (via `key`) each
 * time it opens for a fresh form. Used both by `CrudResource` (the list pages) and
 * standalone — e.g. the transaction form's "+ Add entity/category" buttons, where
 * `preserveState` keeps the transaction dialog open behind it and `onSaved` selects
 * the newly-created record. Text/decimal (comma or dot)/select/textarea/date fields.
 */
export function CrudFormDialog({
    open,
    onOpenChange,
    singular,
    baseUrl,
    fields,
    editing = null,
    fixedValues = {},
    description,
    only,
    onSaved,
}: Props) {
    const form = useForm<Record<string, string>>(
        initialData(fields, fixedValues, editing),
    );

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
            // Keep the current component state (e.g. an open transaction dialog behind
            // this one) so no progress is lost while adding a lookup inline.
            preserveState: true,
            preserveScroll: true,
            ...(only ? { only } : {}),
            onSuccess: (page: Page) => {
                onSaved?.(page);
                onOpenChange(false);
            },
        };

        if (editing) {
            form.patch(`${baseUrl}/${editing.id}`, options);
        } else {
            form.post(baseUrl, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? `Edit ${singular}` : `Add ${singular}`}
                        </DialogTitle>
                        {description && (
                            <DialogDescription>{description}</DialogDescription>
                        )}
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        {fields.map((field) => (
                            <div key={field.key} className="grid gap-2">
                                <Label htmlFor={field.key}>{field.label}</Label>
                                {field.type === 'select' ? (
                                    <Select
                                        value={form.data[field.key]}
                                        onValueChange={(v) =>
                                            form.setData(field.key, v)
                                        }
                                    >
                                        <SelectTrigger id={field.key}>
                                            <SelectValue
                                                placeholder={field.placeholder}
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
                                ) : field.type === 'date' ? (
                                    <DateField
                                        id={field.key}
                                        value={form.data[field.key]}
                                        onChange={(iso) =>
                                            form.setData(field.key, iso)
                                        }
                                        required={field.required}
                                    />
                                ) : field.type === 'textarea' ? (
                                    <Textarea
                                        id={field.key}
                                        value={form.data[field.key]}
                                        onChange={(e) =>
                                            form.setData(
                                                field.key,
                                                e.target.value,
                                            )
                                        }
                                        placeholder={field.placeholder}
                                        required={field.required}
                                        rows={3}
                                    />
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
                                <InputError message={form.errors[field.key]} />
                            </div>
                        ))}
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
