import type { ComponentProps } from 'react';
import { SidebarInset } from '@/components/ui/sidebar';

export function AppContent(props: ComponentProps<'main'>) {
    return <SidebarInset {...props} />;
}
