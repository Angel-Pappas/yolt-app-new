import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Building2,
    Landmark,
    PanelLeftClose,
    PanelLeftOpen,
    Percent,
    Receipt,
    Tags,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

// The day-to-day operational pages.
const financeNavItems: NavItem[] = [
    {
        title: 'Transactions',
        href: '/transactions',
        icon: ArrowLeftRight,
    },
    {
        title: 'Wallets',
        href: '/wallets',
        icon: Wallet,
    },
    {
        title: 'Taxes',
        href: '/taxes',
        icon: Receipt,
    },
    {
        title: 'Entities',
        href: '/entities',
        icon: Building2,
    },
];

// App setup — reference/lookup lists you configure once and leave running.
// This group is meant to grow as more back-office settings are added.
const configNavItems: NavItem[] = [
    {
        title: 'Categories',
        href: '/categories',
        icon: Tags,
    },
    {
        title: 'VAT rates',
        href: '/vat-rates',
        icon: Percent,
    },
    {
        title: 'Withheld tax',
        href: '/withheld-tax-rates',
        icon: Landmark,
    },
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

export function AppSidebar() {
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
                <NavMain items={financeNavItems} label="Finance" />
                <NavMain items={configNavItems} label="Configuration" />
            </SidebarContent>

            <SidebarFooter>
                <CollapseControl />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
