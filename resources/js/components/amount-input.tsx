import { type ComponentProps, useState } from 'react';
import { Input } from '@/components/ui/input';
import { displayAmount, editableAmount } from '@/lib/amounts';

type Props = Omit<ComponentProps<typeof Input>, 'value' | 'onChange'> & {
    value: string;
    onChange: (value: string) => void;
};

/**
 * A money input. Shows the amount Greek-style with thousands dots (`1.234,56`)
 * while not being edited, and as plain text (`1234,56`) while it is — so the dots
 * are only visual and never typed. The value it reports is exactly what was typed;
 * forms turn it into a number with `normalizeAmount` before submitting.
 */
export function AmountInput({
    value,
    onChange,
    onFocus,
    onBlur,
    ...props
}: Props) {
    const [draft, setDraft] = useState<string | null>(null);

    return (
        <Input
            {...props}
            inputMode="decimal"
            value={draft ?? displayAmount(value)}
            onFocus={(e) => {
                setDraft(editableAmount(value));
                onFocus?.(e);
            }}
            onChange={(e) => {
                setDraft(e.target.value);
                onChange(e.target.value);
            }}
            onBlur={(e) => {
                setDraft(null);
                onBlur?.(e);
            }}
        />
    );
}
