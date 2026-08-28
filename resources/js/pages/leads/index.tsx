import { Head } from '@inertiajs/react';

export default function LeadsIndex() {
    return (
        <>
            <Head title="Leads" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Leads</h1>
                    <p className="text-muted-foreground">
                        The Business area is being rebuilt — coming soon.
                    </p>
                </div>
            </div>
        </>
    );
}

LeadsIndex.layout = {
    breadcrumbs: [{ title: 'Leads', href: '/leads' }],
};
