import { CrudResource } from '@/components/crud/crud-resource';
import { formatDate } from '@/lib/format';

export type LeaveData = {
    entries: {
        id: number;
        start_date: string;
        end_date: string;
        days: string;
        note: string | null;
    }[];
    /** This year, then last year. */
    years: {
        year: number;
        entitled: number;
        taken: number;
        remaining: number;
    }[];
};

/** `7` or `7,5` — leave is counted in whole or half days. */
function days(n: number | string): string {
    return String(Number(n)).replace('.', ',');
}

/**
 * An employee's annual leave: what each year gives, what was taken and what is
 * left, and every stretch of leave taken. The days of a new entry are counted
 * from its dates (no weekends or public holidays) unless typed.
 */
export function LeavePanel({
    entityId,
    leave,
}: {
    entityId: number;
    leave: LeaveData;
}) {
    return (
        <CrudResource
            title="Leave"
            singular="leave"
            baseUrl={`/entities/${entityId}/leave`}
            items={leave.entries}
            controls={
                <div className="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                    {leave.years.map((y) => (
                        <div key={y.year} className="flex items-baseline gap-3">
                            <span className="font-medium">{y.year}</span>
                            <span className="text-muted-foreground">
                                {days(y.entitled)} days · {days(y.taken)} taken
                                ·{' '}
                                <span
                                    className={
                                        y.remaining < 0
                                            ? 'font-medium text-rose-600 dark:text-rose-500'
                                            : 'text-foreground font-medium'
                                    }
                                >
                                    {days(y.remaining)} left
                                </span>
                            </span>
                        </div>
                    ))}
                </div>
            }
            columns={[
                {
                    key: 'start_date',
                    label: 'From',
                    filter: { type: 'date' },
                    render: (item) => formatDate(String(item.start_date)),
                },
                {
                    key: 'end_date',
                    label: 'To',
                    filter: { type: 'date' },
                    render: (item) => formatDate(String(item.end_date)),
                },
                {
                    key: 'days',
                    label: 'Days',
                    align: 'right',
                    filter: { type: 'number' },
                    render: (item) => days(String(item.days)),
                },
                {
                    key: 'note',
                    label: 'Note',
                    filter: { type: 'text' },
                    render: (item) => (
                        <span className="text-muted-foreground">
                            {item.note ? String(item.note) : '—'}
                        </span>
                    ),
                },
            ]}
            fields={[
                {
                    key: 'start_date',
                    label: 'From',
                    type: 'date',
                    required: true,
                },
                { key: 'end_date', label: 'To', type: 'date', required: true },
                {
                    key: 'days',
                    label: 'Days (leave empty to count them from the dates)',
                    type: 'decimal',
                },
                { key: 'note', label: 'Note (optional)', type: 'text' },
            ]}
            description="A stretch of annual leave. It counts in the year it starts."
        />
    );
}
