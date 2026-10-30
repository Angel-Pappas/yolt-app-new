import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { type CrudItem, CrudResource } from '@/components/crud/crud-resource';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatAmount, formatDate } from '@/lib/format';

type Aid = {
    id: number;
    programme: string;
    decision_number: string;
    decision_date: string;
    approved_amount: string;
    paid_amount: string | null;
    last_payment_date: string | null;
    beneficiary_name: string;
    beneficiary_vat_number: string;
};

/** Sum of one amount column over the rows shown. */
function total(shown: CrudItem[], key: keyof Aid): string {
    return formatAmount(shown.reduce((sum, row) => sum + Number(row[key]), 0));
}

/** One menu for both formats; each is a plain file link the server builds. */
function DownloadMenu() {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label="Download"
                    title="Download"
                >
                    <Download className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <a href="/de-minimis/download.xlsx" download>
                        Excel (.xlsx)
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href="/de-minimis/download.md" download>
                        Markdown (.md)
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function DeMinimisIndex({ aids }: { aids: Aid[] }) {
    return (
        <>
            <Head title="De minimis" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <CrudResource
                    title="De minimis"
                    singular="grant"
                    baseUrl="/de-minimis"
                    items={aids}
                    toolbar={<DownloadMenu />}
                    columns={[
                        {
                            key: 'programme',
                            label: 'Programme',
                            filter: { type: 'text' },
                            render: (item) => (
                                <span className="block min-w-56 whitespace-normal">
                                    {String(item.programme)}
                                </span>
                            ),
                            footer: (shown) => (
                                <span className="text-muted-foreground font-normal">
                                    {shown.length}{' '}
                                    {shown.length === 1 ? 'grant' : 'grants'}
                                </span>
                            ),
                        },
                        {
                            key: 'decision_number',
                            label: 'Decision no.',
                            filter: { type: 'text' },
                            render: (item) => (
                                <span className="block whitespace-normal">
                                    {String(item.decision_number)}
                                </span>
                            ),
                        },
                        {
                            key: 'decision_date',
                            label: 'Decision date',
                            filter: { type: 'date' },
                            render: (item) =>
                                formatDate(String(item.decision_date)),
                        },
                        {
                            key: 'approved_amount',
                            label: 'Approved',
                            align: 'right',
                            filter: { type: 'number' },
                            render: (item) =>
                                formatAmount(String(item.approved_amount)),
                            footer: (shown) => total(shown, 'approved_amount'),
                        },
                        {
                            key: 'paid_amount',
                            label: 'Paid',
                            align: 'right',
                            filter: { type: 'number' },
                            render: (item) =>
                                item.paid_amount === null
                                    ? '—'
                                    : formatAmount(String(item.paid_amount)),
                            footer: (shown) => total(shown, 'paid_amount'),
                        },
                        {
                            key: 'last_payment_date',
                            label: 'Last payment',
                            filter: { type: 'date' },
                            render: (item) =>
                                item.last_payment_date
                                    ? formatDate(String(item.last_payment_date))
                                    : '—',
                        },
                        {
                            key: 'beneficiary_name',
                            label: 'Beneficiary',
                            filter: { type: 'text' },
                            render: (item) => (
                                <span className="flex flex-col whitespace-nowrap">
                                    <span>{String(item.beneficiary_name)}</span>
                                    <span className="text-muted-foreground text-xs">
                                        VAT{' '}
                                        {String(item.beneficiary_vat_number)}
                                    </span>
                                </span>
                            ),
                        },
                    ]}
                    fields={[
                        {
                            key: 'programme',
                            label: 'Programme / measure / action',
                            type: 'textarea',
                            required: true,
                        },
                        {
                            key: 'decision_number',
                            label: 'Decision or contract no.',
                            type: 'text',
                            required: true,
                        },
                        {
                            key: 'decision_date',
                            label: 'Decision date',
                            type: 'date',
                            required: true,
                        },
                        {
                            key: 'approved_amount',
                            label: 'Approved amount (€)',
                            type: 'money',
                            required: true,
                        },
                        {
                            key: 'paid_amount',
                            label: 'Paid amount (€)',
                            type: 'money',
                        },
                        {
                            key: 'last_payment_date',
                            label: 'Last payment date',
                            type: 'date',
                        },
                        {
                            key: 'beneficiary_name',
                            label: 'Beneficiary',
                            type: 'text',
                            required: true,
                            defaultValue: 'YOLT LABS Ι Κ Ε',
                        },
                        {
                            key: 'beneficiary_vat_number',
                            label: 'Beneficiary VAT no.',
                            type: 'text',
                            required: true,
                            defaultValue: '801487298',
                        },
                    ]}
                    description="A de minimis grant: the approval decision (or contract) and what was paid on it."
                />
            </div>
        </>
    );
}

DeMinimisIndex.layout = {
    breadcrumbs: [{ title: 'De minimis', href: '/de-minimis' }],
};
