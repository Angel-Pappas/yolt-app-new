import { Head, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

/** The landing page after login — a welcome for now; content comes later. */
export default function Home() {
    const { auth, name } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0];

    return (
        <>
            <Head title="Home" />
            <div className="flex flex-1 flex-col items-center justify-center gap-6 p-6 text-center">
                <AppLogoIcon className="size-24" />
                <div className="space-y-2">
                    <h1 className="text-3xl font-semibold">
                        {firstName ? `Welcome, ${firstName}` : 'Welcome'}
                    </h1>
                    <p className="text-muted-foreground">Welcome to {name}.</p>
                </div>
            </div>
        </>
    );
}

Home.layout = {
    breadcrumbs: [{ title: 'Home', href: '/' }],
};
