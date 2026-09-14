import { SidebarTrigger } from '@/components/ui/sidebar';

/**
 * A slim bar shown only on mobile, where the sidebar is an offcanvas sheet and
 * needs a visible trigger to open it. On desktop the collapse control lives in the
 * sidebar footer, so this renders nothing and the page reclaims the vertical space.
 */
export function AppSidebarHeader() {
    return (
        <header className="border-sidebar-border/50 flex h-12 shrink-0 items-center border-b px-4 md:hidden">
            <SidebarTrigger className="-ml-1" />
        </header>
    );
}
