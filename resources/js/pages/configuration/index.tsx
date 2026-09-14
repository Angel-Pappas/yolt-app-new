import { Head, Link, usePage } from '@inertiajs/react';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { configItems } from '@/config-nav';

export default function ConfigurationIndex() {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.is_admin ?? false;
    const items = configItems.filter((item) => !item.adminOnly || isAdmin);

    return (
        <>
            <Head title="Configuration" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Configuration</h1>
                    <p className="text-muted-foreground">
                        Set up the lists that power the app — configure them
                        once and leave them running.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className="block"
                            prefetch
                        >
                            <Card className="hover:border-primary h-full transition-colors">
                                <CardHeader>
                                    <item.icon className="text-muted-foreground size-6" />
                                    <CardTitle className="mt-2">
                                        {item.title}
                                    </CardTitle>
                                    <CardDescription>
                                        {item.description}
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

ConfigurationIndex.layout = {
    breadcrumbs: [{ title: 'Configuration', href: '/configuration' }],
};
