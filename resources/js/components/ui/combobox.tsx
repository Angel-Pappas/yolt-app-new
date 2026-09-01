import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type ComboboxOption = { value: string; label: string };

type Props = {
    options: ComboboxOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    /** Show a "— None —" option that clears the selection. */
    allowNone?: boolean;
    /** When set, offer "+ Create '<query>'" for a non-matching search term. */
    onCreate?: (name: string) => void;
    id?: string;
};

/**
 * A searchable select (shadcn Combobox: Popover + cmdk Command). Handles long
 * option lists that a plain dropdown can't (type to filter), with an optional
 * inline "create" for a term that isn't in the list.
 */
export function Combobox({
    options,
    value,
    onChange,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No results.',
    allowNone,
    onCreate,
    id,
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const selected = options.find((o) => o.value === value);
    const exactMatch = options.some(
        (o) => o.label.toLowerCase() === query.trim().toLowerCase(),
    );

    function choose(v: string) {
        onChange(v);
        setOpen(false);
        setQuery('');
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full justify-between font-normal"
                >
                    <span
                        className={cn('truncate', !selected && 'text-muted-foreground')}
                    >
                        {selected ? selected.label : placeholder}
                    </span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-(--radix-popover-trigger-width) p-0">
                <Command>
                    <CommandInput
                        placeholder={searchPlaceholder}
                        value={query}
                        onValueChange={setQuery}
                    />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {allowNone && (
                                <CommandItem
                                    value="__none__"
                                    onSelect={() => choose('')}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            value === ''
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <span className="text-muted-foreground">
                                        — None —
                                    </span>
                                </CommandItem>
                            )}
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.label}
                                    onSelect={() => choose(option.value)}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            value === option.value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    {option.label}
                                </CommandItem>
                            ))}
                            {onCreate && query.trim() && !exactMatch && (
                                <CommandItem
                                    value={`__create__${query}`}
                                    onSelect={() => {
                                        onCreate(query.trim());
                                        setOpen(false);
                                        setQuery('');
                                    }}
                                >
                                    <Plus className="mr-2 size-4" />
                                    Create &ldquo;{query.trim()}&rdquo;
                                </CommandItem>
                            )}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
