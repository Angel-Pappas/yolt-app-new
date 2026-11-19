import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DateField } from '@/components/ui/date-field';
import { Label } from '@/components/ui/label';
import { formatAmount } from '@/lib/format';

/** `App\Support\Termination::estimate()`. */
type Estimate = {
    date: string;
    hire_date: string | null;
    years: number;
    gross: number;
    notice_months: number;
    lines: { label: string; detail: string; amount: number }[];
    total: number;
};

/**
 * "If they leave": what dismissing the employee on a chosen date would cost —
 * severance, unused leave and the allowance and bonus earned so far — each part
 * listed, in gross amounts. An estimate for planning; nothing is saved.
 */
export function TerminationPanel({ entityId }: { entityId: number }) {
    const [date, setDate] = useState(() =>
        new Date().toISOString().slice(0, 10),
    );
    const [notice, setNotice] = useState(false);
    const [estimate, setEstimate] = useState<Estimate | null>(null);

    useEffect(() => {
        if (!date) return;
        let stale = false;
        const query = new URLSearchParams({ date, notice: notice ? '1' : '0' });
        fetch(`/entities/${entityId}/termination?${query}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (!stale && data) setEstimate(data);
            })
            .catch(() => {});
        return () => {
            stale = true;
        };
    }, [entityId, date, notice]);

    return (
        <Card>
            <CardHeader>
                <CardTitle>If they leave</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="termination_on">Dismissed on</Label>
                        <DateField
                            id="termination_on"
                            value={date}
                            showCalendar={false}
                            onChange={setDate}
                        />
                    </div>
                    <label className="flex h-9 items-center gap-2 text-sm">
                        <Checkbox
                            checked={notice}
                            onCheckedChange={(v) => setNotice(v === true)}
                        />
                        Notice given
                        {estimate && estimate.notice_months > 0 && (
                            <span className="text-muted-foreground">
                                ({estimate.notice_months} month
                                {estimate.notice_months === 1 ? '' : 's'} by
                                law)
                            </span>
                        )}
                    </label>
                </div>

                {estimate &&
                    (estimate.hire_date === null ? (
                        <p className="text-muted-foreground text-sm">
                            Set the hire date above to calculate this.
                        </p>
                    ) : estimate.gross === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No monthly salary is set up on that date — add a
                            payroll recurring transaction to calculate this.
                        </p>
                    ) : (
                        <div className="grid gap-2 text-sm">
                            {estimate.lines.map((line) => (
                                <div
                                    key={line.label}
                                    className="flex items-baseline justify-between gap-4"
                                >
                                    <div>
                                        <span className="font-medium">
                                            {line.label}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {line.detail}
                                        </span>
                                    </div>
                                    <span className="tabular-nums">
                                        {formatAmount(line.amount)}
                                    </span>
                                </div>
                            ))}
                            <div className="flex items-baseline justify-between gap-4 border-t pt-2 font-semibold">
                                <span>Total (gross)</span>
                                <span className="tabular-nums">
                                    {formatAmount(estimate.total)}
                                </span>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                On a monthly salary of{' '}
                                {formatAmount(estimate.gross)}. Severance up to
                                60.000 € is tax-free; the other parts are
                                ordinary pay with the usual deductions.
                            </p>
                        </div>
                    ))}
            </CardContent>
        </Card>
    );
}
