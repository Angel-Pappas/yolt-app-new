import { Head, router, useRemember } from '@inertiajs/react';
import {
    type Agreement,
    AgreementsTable,
} from '@/components/agreements/agreements-table';
import { ShowAllToggle } from '@/components/data-table/show-all-toggle';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const WARNING_CHOICES = [15, 30, 45, 60, 90];

/**
 * Agreements with customers, suppliers, contractors and employees: the file and
 * dates of each, and which are about to end. Ended ones only show (green) with
 * "Show all". The warning window — how far ahead an ending agreement is flagged,
 * here and on Home — is set on this page.
 */
export default function AgreementsIndex({
    agreements,
    warning_days,
}: {
    agreements: Agreement[];
    warning_days: number;
}) {
    const [showAll, setShowAll] = useRemember(false, 'agreements-show-all');
    const rows = showAll
        ? agreements
        : agreements.filter((a) => a.state !== 'ended');
    const choices = WARNING_CHOICES.includes(warning_days)
        ? WARNING_CHOICES
        : [...WARNING_CHOICES, warning_days].sort((a, b) => a - b);

    return (
        <>
            <Head title="Agreements" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <AgreementsTable
                    agreements={rows}
                    emptyMessage={
                        showAll
                            ? 'No agreements yet.'
                            : 'No current agreements.'
                    }
                    controls={
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="text-muted-foreground">
                                Warn when an agreement ends within
                            </span>
                            <Select
                                value={String(warning_days)}
                                onValueChange={(v) =>
                                    router.patch(
                                        '/agreements/warning',
                                        { warning_days: Number(v) },
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-28"
                                    aria-label="Warning window"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {choices.map((n) => (
                                        <SelectItem key={n} value={String(n)}>
                                            {n} days
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <ShowAllToggle on={showAll} onChange={setShowAll} />
                        </div>
                    }
                />
            </div>
        </>
    );
}

AgreementsIndex.layout = {
    breadcrumbs: [{ title: 'Agreements', href: '/agreements' }],
};
