import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Option = { id: number; name: string };

export type ProjectFilters = {
    q: string | null;
    status: number | null;
};

type Props = {
    filters: ProjectFilters;
    statuses: Option[];
};

const ALL = 'all';

export function ProjectsFilters({ filters, statuses }: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    function apply(next: Partial<ProjectFilters>) {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        if (merged.q) params.q = merged.q;
        if (merged.status) params.status = String(merged.status);

        router.get('/projects', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function onSearchChange(value: string) {
        setSearch(value);
        if (debounce.current) {
            clearTimeout(debounce.current);
        }
        debounce.current = setTimeout(() => apply({ q: value || null }), 300);
    }

    function clearAll() {
        setSearch('');
        router.get(
            '/projects',
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const hasFilters = Boolean(filters.q || filters.status);

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Input
                placeholder="Search…"
                value={search}
                onChange={(e) => onSearchChange(e.target.value)}
                className="w-48"
                aria-label="Search projects"
            />

            <Select
                value={filters.status ? String(filters.status) : ALL}
                onValueChange={(v) =>
                    apply({ status: v === ALL ? null : Number(v) })
                }
            >
                <SelectTrigger className="w-40">
                    <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>All statuses</SelectItem>
                    {statuses.map((s) => (
                        <SelectItem key={s.id} value={String(s.id)}>
                            {s.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {hasFilters && (
                <Button variant="ghost" size="sm" onClick={clearAll}>
                    <X className="size-4" />
                    Clear
                </Button>
            )}
        </div>
    );
}
