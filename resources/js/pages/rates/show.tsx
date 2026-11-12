import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { CrudResource } from '@/components/crud/crud-resource';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';

type Props = {
    rate: { id: number; name: string; rate: string };
    changes: { id: number; valid_from: string; rate: string }[];
    /** The rate list this one belongs to (VAT rates or Withheld tax). */
    baseUrl: string;
    listTitle: string;
};

/**
 * A VAT or withholding rate's own page: its dated changes. The rate's own
 * percentage applies until the first change; from each change's date on, that
 * change's percentage does — for whatever is invoiced from that day.
 */
export default function RateShow({ rate, changes, baseUrl, listTitle }: Props) {
    return (
        <>
            <Head title={rate.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <Button variant="ghost" size="icon" asChild>
                        <Link
                            href={baseUrl}
                            aria-label={`Back to ${listTitle}`}
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold">{rate.name}</h1>
                    <span className="text-muted-foreground tabular-nums">
                        {Number(rate.rate).toFixed(2)}% until changed
                    </span>
                </div>

                <CrudResource
                    title="Changes"
                    singular="change"
                    baseUrl={`${baseUrl}/${rate.id}/changes`}
                    items={changes}
                    columns={[
                        {
                            key: 'valid_from',
                            label: 'From',
                            filter: { type: 'date' },
                            render: (item) =>
                                formatDate(String(item.valid_from)),
                        },
                        {
                            key: 'rate',
                            label: 'Rate',
                            align: 'right',
                            filter: { type: 'number' },
                            render: (item) =>
                                `${Number(item.rate).toFixed(2)}%`,
                        },
                    ]}
                    fields={[
                        {
                            key: 'valid_from',
                            label: 'From (first day it applies)',
                            type: 'date',
                            required: true,
                        },
                        {
                            key: 'rate',
                            label: 'Rate (%)',
                            type: 'decimal',
                            required: true,
                        },
                    ]}
                    description="From this date on the rate has this percentage. Everything not yet reconciled and invoiced from that date is recalculated."
                />
            </div>
        </>
    );
}

RateShow.layout = {
    breadcrumbs: [{ title: 'Configuration', href: '/configuration' }],
};
