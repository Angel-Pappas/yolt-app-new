import { useEffect, useRef, useState } from 'react';
import { compactAmount, niceTicks, shortMonth } from '@/lib/chart';
import { formatAmount, formatMonthYear } from '@/lib/format';
import { cn } from '@/lib/utils';

export type CashFlowPoint = {
    month: string;
    income: number;
    expenses: number;
    ending: number;
};

const MARGIN = { top: 12, right: 12, bottom: 26, left: 44 };

/**
 * Income and expenses per month as paired bars, with the month-end balance as a
 * line over them, on one euro scale. The current month is marked, so the past
 * (what happened) and the future (what is planned) are told apart at a glance.
 * Hovering a month shows its figures. Drawn as plain SVG — no chart library.
 */
export function CashFlowChart({
    points,
    currentMonth,
    height = 300,
    className,
}: {
    points: CashFlowPoint[];
    currentMonth: string;
    height?: number;
    className?: string;
}) {
    const wrapRef = useRef<HTMLDivElement | null>(null);
    const [width, setWidth] = useState(800);
    const [hover, setHover] = useState<number | null>(null);

    useEffect(() => {
        const el = wrapRef.current;
        if (!el) return;
        const observer = new ResizeObserver(() => setWidth(el.clientWidth));
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    if (points.length === 0) return null;

    const values = points.flatMap((p) => [p.income, p.expenses, p.ending]);
    const ticks = niceTicks(Math.min(...values), Math.max(...values));
    const min = ticks[0];
    const max = ticks[ticks.length - 1];

    const plotW = Math.max(10, width - MARGIN.left - MARGIN.right);
    const plotH = height - MARGIN.top - MARGIN.bottom;
    const band = plotW / points.length;
    const y = (v: number) => MARGIN.top + plotH * (1 - (v - min) / (max - min));
    const x = (i: number) => MARGIN.left + band * i;
    const barW = Math.max(2, Math.min(18, band * 0.3));
    // Thin the month labels out when they would collide.
    const labelEvery = Math.ceil(52 / band);

    const line = points
        .map(
            (p, i) => `${i === 0 ? 'M' : 'L'}${x(i) + band / 2},${y(p.ending)}`,
        )
        .join(' ');
    const hovered = hover !== null ? points[hover] : null;

    return (
        <div className={cn('flex min-w-0 flex-col gap-2', className)}>
            <div className="text-muted-foreground flex flex-wrap items-center gap-4 text-xs">
                <span className="flex items-center gap-1.5">
                    <span className="size-2.5 rounded-sm bg-emerald-500" />
                    Income
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="size-2.5 rounded-sm bg-rose-500" />
                    Expenses
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="bg-foreground h-0.5 w-4 rounded" />
                    Balance at month end
                </span>
            </div>

            {/* The box takes its width from the layout (never from the drawing,
                which is laid over it), so the chart always fits its container. */}
            <div
                ref={wrapRef}
                className="relative w-full min-w-0"
                style={{ height }}
            >
                <svg
                    width={width}
                    height={height}
                    className="absolute top-0 left-0"
                    role="img"
                    aria-label="Income, expenses and balance per month"
                    onMouseLeave={() => setHover(null)}
                >
                    {ticks.map((t) => (
                        <g key={t}>
                            <line
                                x1={MARGIN.left}
                                x2={width - MARGIN.right}
                                y1={y(t)}
                                y2={y(t)}
                                className={
                                    t === 0
                                        ? 'stroke-foreground/40'
                                        : 'stroke-border'
                                }
                            />
                            <text
                                x={MARGIN.left - 6}
                                y={y(t)}
                                textAnchor="end"
                                dominantBaseline="middle"
                                className="fill-muted-foreground text-[10px] tabular-nums"
                            >
                                {compactAmount(t)}
                            </text>
                        </g>
                    ))}

                    {points.map((p, i) => {
                        const cx = x(i) + band / 2;
                        const isNow = p.month === currentMonth;
                        return (
                            <g key={p.month}>
                                {(hover === i || isNow) && (
                                    <rect
                                        x={x(i)}
                                        y={MARGIN.top}
                                        width={band}
                                        height={plotH}
                                        className={
                                            hover === i
                                                ? 'fill-foreground/10'
                                                : 'fill-foreground/5'
                                        }
                                    />
                                )}
                                <rect
                                    x={cx - barW - 1}
                                    y={y(Math.max(p.income, 0))}
                                    width={barW}
                                    height={Math.abs(y(p.income) - y(0))}
                                    rx={1.5}
                                    className="fill-emerald-500"
                                />
                                <rect
                                    x={cx + 1}
                                    y={y(Math.max(p.expenses, 0))}
                                    width={barW}
                                    height={Math.abs(y(p.expenses) - y(0))}
                                    rx={1.5}
                                    className="fill-rose-500"
                                />
                                {i % labelEvery === 0 && (
                                    <text
                                        x={cx}
                                        y={height - 8}
                                        textAnchor="middle"
                                        className={cn(
                                            'text-[10px]',
                                            isNow
                                                ? 'fill-foreground font-semibold'
                                                : 'fill-muted-foreground',
                                        )}
                                    >
                                        {shortMonth(p.month)}
                                    </text>
                                )}
                            </g>
                        );
                    })}

                    <path
                        d={line}
                        fill="none"
                        strokeWidth={2}
                        strokeLinejoin="round"
                        className="stroke-foreground"
                    />
                    {points.map((p, i) => (
                        <circle
                            key={p.month}
                            cx={x(i) + band / 2}
                            cy={y(p.ending)}
                            r={hover === i ? 4 : 2.5}
                            className={cn(
                                'stroke-background',
                                p.ending < 0
                                    ? 'fill-rose-600'
                                    : 'fill-foreground',
                            )}
                        />
                    ))}

                    {/* One hover target per month, over everything. */}
                    {points.map((p, i) => (
                        <rect
                            key={p.month}
                            x={x(i)}
                            y={MARGIN.top}
                            width={band}
                            height={plotH + MARGIN.bottom}
                            fill="transparent"
                            onMouseEnter={() => setHover(i)}
                        />
                    ))}
                </svg>

                {hovered && hover !== null && (
                    <div
                        className="bg-popover text-popover-foreground pointer-events-none absolute top-2 z-10 grid min-w-44 gap-1 rounded-md border p-2 text-xs shadow-md"
                        style={
                            x(hover) + band / 2 > width / 2
                                ? { right: width - x(hover) + 6 }
                                : { left: x(hover) + band + 6 }
                        }
                    >
                        <div className="font-medium">
                            {formatMonthYear(hovered.month)}
                        </div>
                        <div className="flex justify-between gap-4 tabular-nums">
                            <span className="text-muted-foreground">
                                Income
                            </span>
                            {formatAmount(hovered.income)}
                        </div>
                        <div className="flex justify-between gap-4 tabular-nums">
                            <span className="text-muted-foreground">
                                Expenses
                            </span>
                            {formatAmount(hovered.expenses)}
                        </div>
                        <div className="flex justify-between gap-4 font-medium tabular-nums">
                            <span className="text-muted-foreground font-normal">
                                Balance
                            </span>
                            {formatAmount(hovered.ending)}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
