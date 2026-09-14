import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowLeftRight,
    Building2,
    PanelLeftClose,
    PanelLeftOpen,
    Receipt,
    Settings2,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { configItems, isConfigPath } from '@/config-nav';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

// The day-to-day operational pages.
const financeNavItems: NavItem[] = [
    { title: 'Transactions', href: '/transactions', icon: ArrowLeftRight },
    { title: 'Wallets', href: '/wallets', icon: Wallet },
    { title: 'Taxes', href: '/taxes', icon: Receipt },
    { title: 'Entities', href: '/entities', icon: Building2 },
];

/** The sidebar collapse/expand toggle, living at the bottom of the panel. */
function CollapseControl() {
    const { toggleSidebar, state } = useSidebar();
    const collapsed = state === 'collapsed';

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    onClick={toggleSidebar}
                    tooltip={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    className="text-sidebar-foreground/70"
                >
                    {collapsed ? <PanelLeftOpen /> : <PanelLeftClose />}
                    <span>Collapse</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

/** A single full-width sidebar link (used for the Configuration entry and the
 *  back-to-app link), styled like a nav item but standing on its own. */
function SidebarLink({
    href,
    label,
    icon: Icon,
}: {
    href: string;
    label: string;
    icon: typeof Settings2;
}) {
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton asChild tooltip={label}>
                        <Link href={href} prefetch>
                            <Icon />
                            <span>{label}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    );
}

export function AppSidebar() {
    const { currentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const inConfig = isConfigPath(currentUrl);
    const isAdmin = auth.user?.is_admin ?? false;

    const configNavItems: NavItem[] = configItems
        .filter((item) => !item.adminOnly || isAdmin)
        .map((item) => ({
            title: item.title,
            href: item.href,
            icon: item.icon,
        }));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/transactions" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {inConfig ? (
                    <>
                        <SidebarLink
                            href="/transactions"
                            label="Back to app"
                            icon={ArrowLeft}
                        />
                        <NavMain items={configNavItems} label="Configuration" />
                    </>
                ) : (
                    <>
                        <NavMain items={financeNavItems} label="Finance" />
                        <SidebarLink
                            href="/configuration"
                            label="Configuration"
                            icon={Settings2}
                        />
                    </>
                )}
            </SidebarContent>

            <SidebarFooter>
                <CollapseControl />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
