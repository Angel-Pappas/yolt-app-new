import { Head } from '@inertiajs/react';

export default function TransactionsIndex() {
    return (
        <>
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Transactions</h1>
                    <p className="text-muted-foreground">
                        The Finance area is being rebuilt — coming soon.
                    </p>
                </div>
            </div>
        </>
    );
}

TransactionsIndex.layout = {
    breadcrumbs: [{ title: 'Transactions', href: '/transactions' }],
};
