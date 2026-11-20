import { Head, router } from '@inertiajs/react';
import { SubTabs } from '@/components/page-tabs';
import { formatDate } from '@/lib/format';
import { type MatchItem, NotMatchedView } from './not-matched-view';
import {
    type HistoryRow,
    StatementsView,
    type WalletRow,
} from './statements-view';
import { type Work, WorkView } from './work-view';

type Tab = 'statements' | 'work' | 'not-matched';

type Props = {
    tab: Tab;
    wallets: WalletRow[];
    history: HistoryRow[];
    statement: Work | null;
    notMatched: MatchItem[];
};

/**
 * Accounting ▸ Bank import: **Statements** (every upload and hand conclusion — where
 * the books stand), **Working on** a statement (its draft: decide each line, undo,
 * conclude), and **Not matched yet** (planned items nothing paid in a concluded
 * period).
 */
export default function BankImportIndex({
    tab,
    wallets,
    history,
    statement,
    notMatched,
}: Props) {
    const go = (next: Tab) =>
        router.get(
            '/bank-import',
            { tab: next, ...(statement ? { statement: statement.id } : {}) },
            { preserveScroll: true },
        );

    const heading = (
        <SubTabs<Tab>
            label="Bank import"
            active={tab}
            onChange={go}
            tabs={[
                { key: 'statements', label: 'Statements' },
                ...(statement
                    ? [
                          {
                              key: 'work' as Tab,
                              label: `${statement.open ? 'Working on' : 'Statement'} ${formatDate(statement.from)}–${formatDate(statement.to)}`,
                          },
                      ]
                    : []),
                {
                    key: 'not-matched',
                    label:
                        notMatched.length > 0
                            ? 'Not matched yet !'
                            : 'Not matched yet',
                },
            ]}
        />
    );

    return (
        <>
            <Head title="Bank import" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {tab === 'work' && statement ? (
                    <WorkView statement={statement} heading={heading} />
                ) : tab === 'not-matched' ? (
                    <NotMatchedView items={notMatched} heading={heading} />
                ) : (
                    <StatementsView
                        history={history}
                        wallets={wallets}
                        heading={heading}
                    />
                )}
            </div>
        </>
    );
}
