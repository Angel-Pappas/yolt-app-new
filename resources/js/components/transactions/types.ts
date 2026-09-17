export type TransactionType = 'income' | 'expense' | 'transfer';

type Related = { id: number; name: string } | null;
type VatLine = { net: string; vat_rate_id: number | null; position: number };
type WithheldLine = {
    net: string;
    withheld_rate_id: number | null;
    position: number;
};

/**
 * The canonical shape of a transaction row anywhere it's listed — matches the
 * server's `Transaction::withListData()` scope. The shared `TransactionsTable`
 * renders and edits this shape, so every list of transactions provides exactly it.
 */
export type Transaction = {
    id: number;
    date: string;
    invoice_date: string;
    description: string;
    type: TransactionType;
    net: string;
    vat_amount: string;
    withheld_amount: string;
    fmy_amount: string | null;
    efka_employee_amount: string | null;
    efka_employer_amount: string | null;
    is_reconciled: boolean;
    invoice_month: number | null;
    invoice_not_required: boolean;
    entity_id: number | null;
    category_id: number | null;
    wallet_id: number;
    to_wallet_id: number | null;
    vat_rate_id: number | null;
    wallet: Related;
    to_wallet: Related;
    entity: Related;
    category: Related;
    vat_lines: VatLine[];
    withheld_lines: WithheldLine[];
    /** Present only in balance view: the running balance after this row. */
    balance?: string | number;
};

/**
 * The cash a transaction moves: net + VAT − withheld − FMY − employee EFKA. The
 * payroll amounts are null (→ 0) on every non-payroll row, so this is the plain
 * net + VAT − withheld elsewhere. Employer EFKA is a liability, never cash out.
 */
export function transactionTotal(t: Transaction): number {
    return (
        Number(t.net) +
        Number(t.vat_amount) -
        Number(t.withheld_amount) -
        Number(t.fmy_amount ?? 0) -
        Number(t.efka_employee_amount ?? 0)
    );
}
