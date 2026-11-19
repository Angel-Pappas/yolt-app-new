import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowLeftRight,
    CalendarRange,
    ChartNoAxesCombined,
    FileSignature,
    FileUp,
    FolderKanban,
    HandCoins,
    House,
    PanelLeftClose,
    PanelLeftOpen,
    Settings2,
    Users,
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
import { ENTITY_TABS, lastTab, PLANNING_TABS } from '@/components/page-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

// Finance: what Angel goes through with the CEO — the plan, where it leads, the
// projects behind it. Planning opens on its tab last used.
const financeNavItems = (): NavItem[] => [
    {
        title: 'Planning',
        href: lastTab('planning', PLANNING_TABS),
        section: '/planning',
        icon: CalendarRange,
    },
    { title: 'Projection', href: '/projection', icon: ChartNoAxesCombined },
    {
        title: 'Projects',
        href: '/projects',
        section: '/projects',
        icon: FolderKanban,
    },
];

// Accounting: the books, kept monthly. Entities opens on its tab last used; Bank
// import shows "!" while planned items wait unmatched in a concluded period.
const accountingNavItems = (notMatched: number): NavItem[] => [
    { title: 'Transactions', href: '/transactions', icon: ArrowLeftRight },
    {
        title: 'Bank import',
        href: '/bank-import',
        icon: FileUp,
        attention: notMatched > 0,
    },
    { title: 'Wallets', href: '/wallets', icon: Wallet },
    {
        title: 'Entities',
        href: lastTab('entities', ENTITY_TABS),
        section: '/entities',
        icon: Users,
    },
    { title: 'Agreements', href: '/agreements', icon: FileSignature },
    { title: 'De minimis', href: '/de-minimis', icon: HandCoins },
    { title: 'Configuration', href: '/configuration', icon: Settings2 },
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

// The landing page, above the sections.
const homeNavItems: NavItem[] = [{ title: 'Home', href: '/', icon: House }];

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
    const { auth, attention } = usePage().props;
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
                            <Link href="/" prefetch>
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
                        <NavMain items={homeNavItems} />
                        <NavMain items={financeNavItems()} label="Finance" />
                        <NavMain
                            items={accountingNavItems(
                                attention?.not_matched ?? 0,
                            )}
                            label="Accounting"
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
