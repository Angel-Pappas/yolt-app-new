import {
    CalendarDays,
    Landmark,
    type LucideIcon,
    Percent,
    Tags,
    Users,
} from 'lucide-react';

/** An item in the Configuration area — a setup list you open, configure, and leave
 *  running. Shared by the sidebar (in config mode) and the Configuration tiles page. */
export type ConfigItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    description: string;
    /** Only shown to admins (e.g. Users). */
    adminOnly?: boolean;
};

export const configItems: ConfigItem[] = [
    {
        title: 'Categories',
        href: '/configuration/categories',
        icon: Tags,
        description: 'Income and expense categories for tagging transactions.',
    },
    {
        title: 'VAT rates',
        href: '/configuration/vat-rates',
        icon: Percent,
        description:
            'The VAT percentages available when entering transactions.',
    },
    {
        title: 'Withheld tax',
        href: '/configuration/withheld-tax-rates',
        icon: Landmark,
        description: 'Withholding-tax rates kept back on contractor payments.',
    },
    {
        title: 'Public holidays',
        href: '/configuration/public-holidays',
        icon: CalendarDays,
        description:
            'Holiday dates that (with weekends) shift tax payment dates back.',
    },
    {
        title: 'Users',
        href: '/configuration/users',
        icon: Users,
        description: 'Invite people to the app and manage admin access.',
        adminOnly: true,
    },
];

const configPaths = ['/configuration', ...configItems.map((i) => i.href)];

/** Whether a pathname is inside the Configuration area (its landing or any list). */
export function isConfigPath(path: string): boolean {
    return configPaths.some((p) => path === p || path.startsWith(`${p}/`));
}
