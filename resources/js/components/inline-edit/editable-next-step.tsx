import { useState } from 'react';
import { Input } from '@/components/ui/input';

/**
 * A click-to-edit "Next step" cell for a list row. Shows the text; clicking turns
 * it into an input that saves on Enter or blur (only when changed). `onSave` is
 * expected to PATCH the new value.
 */
export function EditableNextStep({
    value,
    onSave,
}: {
    value: string | null;
    onSave: (value: string) => void;
}) {
    const [editing, setEditing] = useState(false);
    const [text, setText] = useState(value ?? '');

    function save() {
        setEditing(false);
        if (text !== (value ?? '')) {
            onSave(text);
        }
    }

    if (editing) {
        return (
            <Input
                autoFocus
                value={text}
                onChange={(e) => setText(e.target.value)}
                onBlur={save}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        save();
                    } else if (e.key === 'Escape') {
                        setText(value ?? '');
                        setEditing(false);
                    }
                }}
                className="h-8"
            />
        );
    }

    return (
        <button
            type="button"
            onClick={() => {
                setText(value ?? '');
                setEditing(true);
            }}
            className="text-muted-foreground hover:text-foreground w-full text-left"
        >
            {value || '—'}
        </button>
    );
}
