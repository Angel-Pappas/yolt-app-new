import { Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { readStored, writeStored } from '@/lib/stored-state';
import { cn } from '@/lib/utils';

export type PageTab = { label: string; href: string };

/** Finance ▸ Planning: what the company expects, one tab each. */
export const PLANNING_TABS: PageTab[] = [
    { label: 'Income', href: '/planning/income' },
    { label: 'Recurring', href: '/planning/recurring' },
    { label: 'One-offs', href: '/planning/one-offs' },
    { label: 'Taxes', href: '/planning/taxes' },
];

/** Accounting ▸ Entities: one tab per counterparty type. */
export const ENTITY_TABS: PageTab[] = [
    { label: 'Customers', href: '/entities/customers' },
    { label: 'Suppliers', href: '/entities/suppliers' },
    { label: 'Contractors', href: '/entities/contractors' },
    { label: 'Employees', href: '/entities/employees' },
    { label: 'Shareholders', href: '/entities/shareholders' },
    { label: 'State', href: '/entities/state' },
];

const storeKey = (group: string) => `tabs:${group}`;

/** The tab of a tabbed page last shown in this browser tab (for the sidebar link). */
export function lastTab(group: string, tabs: PageTab[]): string {
    const stored = readStored<string | null>(storeKey(group), null);

    return tabs.some((t) => t.href === stored)
        ? (stored as string)
        : tabs[0].href;
}

/**
 * The tab bar of a page made of several lists (Planning, Entities), shown where a
 * list's title goes. Each tab is its own address (a link — middle-click opens it in
 * a new browser tab), names only. The tab shown is remembered, so the sidebar link
 * reopens it.
 */
export function PageTabs({
    group,
    tabs,
    active,
}: {
    group: string;
    tabs: PageTab[];
    /** The href of the tab this page is. */
    active: string;
}) {
    useEffect(() => writeStored(storeKey(group), active), [group, active]);

    return (
        <nav
            aria-label={group}
            className="bg-muted inline-flex flex-wrap gap-1 rounded-lg p-1"
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.href}
                    href={tab.href}
                    prefetch
                    aria-current={tab.href === active ? 'page' : undefined}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        tab.href === active
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}

/**
 * Tabs inside one page (e.g. a customer's Payments · Projects · …): same look as
 * {@see PageTabs}, switching what the page shows below.
 */
export function SubTabs<T extends string>({
    label,
    tabs,
    active,
    onChange,
}: {
    label: string;
    tabs: { key: T; label: string }[];
    active: T;
    onChange: (key: T) => void;
}) {
    return (
        <div
            role="tablist"
            aria-label={label}
            className="bg-muted inline-flex flex-wrap gap-1 self-start rounded-lg p-1"
        >
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    role="tab"
                    aria-selected={tab.key === active}
                    onClick={() => onChange(tab.key)}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        tab.key === active
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                    )}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}
