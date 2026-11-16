import * as React from 'react';

import { cn } from '@/lib/utils';

function Table({
    className,
    containerClassName,
    ...props
}: React.ComponentProps<'table'> & { containerClassName?: string }) {
    return (
        <div
            data-slot="table-container"
            className={cn('relative w-full overflow-x-auto', containerClassName)}
        >
            <table
                data-slot="table"
                className={cn('w-full caption-bottom text-sm', className)}
                {...props}
            />
        </div>
    );
}

function TableHeader({ className, ...props }: React.ComponentProps<'thead'>) {
    return (
        <thead
            data-slot="table-header"
            className={cn('[&_tr]:border-b', className)}
            {...props}
        />
    );
}

function TableBody({ className, ...props }: React.ComponentProps<'tbody'>) {
    return (
        <tbody
            data-slot="table-body"
            className={cn('[&_tr:last-child]:border-0', className)}
            {...props}
        />
    );
}

function TableFooter({ className, ...props }: React.ComponentProps<'tfoot'>) {
    return (
        <tfoot
            data-slot="table-footer"
            className={cn(
                'bg-muted/50 border-t font-medium [&>tr]:last:border-b-0',
                className,
            )}
            {...props}
        />
    );
}

function TableRow({ className, ...props }: React.ComponentProps<'tr'>) {
    return (
        <tr
            data-slot="table-row"
            className={cn(
                'hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors',
                className,
            )}
            {...props}
        />
    );
}

function TableHead({ className, ...props }: React.ComponentProps<'th'>) {
    return (
        <th
            data-slot="table-head"
            className={cn(
                'text-muted-foreground h-10 px-1.5 text-left align-middle leading-tight font-medium [&:has([role=checkbox])]:pr-0',
                className,
            )}
            {...props}
        />
    );
}

/** A body cell. Icon buttons in a row (its actions) are compact, so a row's
 *  buttons fit beside its data on a laptop screen. */
function TableCell({ className, ...props }: React.ComponentProps<'td'>) {
    return (
        <td
            data-slot="table-cell"
            className={cn(
                'px-1.5 py-3 align-middle [&:has([role=checkbox])]:pr-0 [&_[data-slot=button][data-size=icon]]:size-7',
                className,
            )}
            {...props}
        />
    );
}

function TableCaption({
    className,
    ...props
}: React.ComponentProps<'caption'>) {
    return (
        <caption
            data-slot="table-caption"
            className={cn('text-muted-foreground mt-4 text-sm', className)}
            {...props}
        />
    );
}

export {
    Table,
    TableHeader,
    TableBody,
    TableFooter,
    TableHead,
    TableRow,
    TableCell,
    TableCaption,
};
