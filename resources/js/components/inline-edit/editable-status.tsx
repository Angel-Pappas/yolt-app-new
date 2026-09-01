import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Option = { id: number; name: string };

/**
 * An always-editable status cell for a list row: a compact borderless select that
 * saves immediately on change. `onSave` receives the chosen id as a string (empty
 * for "— None —") and is expected to PATCH it.
 */
export function EditableStatus({
    value,
    options,
    onSave,
}: {
    value: number | null;
    options: Option[];
    onSave: (statusId: string) => void;
}) {
    return (
        <Select
            value={value ? String(value) : 'none'}
            onValueChange={(v) => onSave(v === 'none' ? '' : v)}
        >
            <SelectTrigger className="h-8 w-auto gap-1 border-0 bg-transparent px-2 shadow-none focus-visible:ring-0 dark:bg-transparent">
                <SelectValue placeholder="—" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="none">— None —</SelectItem>
                {options.map((o) => (
                    <SelectItem key={o.id} value={String(o.id)}>
                        {o.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
