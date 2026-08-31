import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatAmount } from '@/lib/format';

type Amount = number | string;

type Props = {
    vat: { payable_this_month: Amount; net: Amount };
    withheld: { payable_this_month: Amount; withheld: Amount };
};

function Figure({ label, value }: { label: string; value: Amount }) {
    return (
        <div>
            <div className="text-muted-foreground text-xs">{label}</div>
            <div className="text-lg font-semibold tabular-nums">
                {formatAmount(value)}
            </div>
        </div>
    );
}

function TaxCard({
    href,
    title,
    primary,
    secondary,
}: {
    href: string;
    title: string;
    primary: { label: string; value: Amount };
    secondary: { label: string; value: Amount };
}) {
    return (
        <Link href={href} className="block">
            <Card className="hover:border-primary/50 transition-colors">
                <CardHeader className="flex-row items-center justify-between space-y-0">
                    <CardTitle>{title}</CardTitle>
                    <ChevronRight className="text-muted-foreground size-4" />
                </CardHeader>
                <CardContent className="flex gap-8">
                    <Figure label={primary.label} value={primary.value} />
                    <Figure label={secondary.label} value={secondary.value} />
                </CardContent>
            </Card>
        </Link>
    );
}

export default function TaxesIndex({ vat, withheld }: Props) {
    return (
        <>
            <Head title="Taxes" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-2xl font-semibold">Taxes</h1>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <TaxCard
                        href="/taxes/vat"
                        title="VAT"
                        primary={{
                            label: 'Payable this month',
                            value: vat.payable_this_month,
                        }}
                        secondary={{
                            label: "This month's net",
                            value: vat.net,
                        }}
                    />
                    <TaxCard
                        href="/taxes/withheld"
                        title="Withholding tax"
                        primary={{
                            label: 'Payable this month',
                            value: withheld.payable_this_month,
                        }}
                        secondary={{
                            label: 'Withheld this month',
                            value: withheld.withheld,
                        }}
                    />
                </div>
            </div>
        </>
    );
}

TaxesIndex.layout = {
    breadcrumbs: [{ title: 'Taxes', href: '/taxes' }],
};
