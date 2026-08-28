import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeftRight, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

type AreaCard = {
    title: string;
    description: string;
    href: string;
    icon: LucideIcon;
};

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth.user;

    const areas: AreaCard[] = [];
    if (user.can_access_finance) {
        areas.push({
            title: 'Finance',
            description: 'Transactions, wallets, entities and taxes.',
            href: '/transactions',
            icon: ArrowLeftRight,
        });
    }
    if (user.can_access_crm) {
        areas.push({
            title: 'Business',
            description: 'Leads and projects.',
            href: '/leads',
            icon: Users,
        });
    }

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Welcome back, {user.name}
                    </h1>
                    <p className="text-muted-foreground">
                        Choose an area to get started.
                    </p>
                </div>

                {areas.length > 0 ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {areas.map((area) => (
                            <Link
                                key={area.title}
                                href={area.href}
                                className="block"
                            >
                                <Card className="hover:border-primary h-full transition-colors">
                                    <CardHeader>
                                        <area.icon className="text-muted-foreground size-6" />
                                        <CardTitle className="mt-2">
                                            {area.title}
                                        </CardTitle>
                                        <CardDescription>
                                            {area.description}
                                        </CardDescription>
                                    </CardHeader>
                                </Card>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <p className="text-muted-foreground">
                        You do not have access to any areas yet. An
                        administrator can grant you access.
                    </p>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
