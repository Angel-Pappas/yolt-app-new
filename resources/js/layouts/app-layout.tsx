import type { ReactNode } from 'react';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';

export default function AppLayout({ children }: { children: ReactNode }) {
    return <AppLayoutTemplate>{children}</AppLayoutTemplate>;
}
