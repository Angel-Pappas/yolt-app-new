/** A customer payment line as the lists show it (see `PaymentController::rows`). */
export type PaymentRow = {
    key: string;
    id: number;
    entity_id: number;
    entity: string;
    category_id: number | null;
    wallet_id: number;
    vat_rate_id: number | null;
    date: string;
    invoice_date: string;
    description: string;
    net: number;
    vat: number;
    total: number;
    paid: number;
    open: number;
    /** Unpaid and dated in a concluded period of its wallet. */
    overdue: boolean;
    excluded: boolean;
    is_issued: boolean;
    /** The invoice date no longer follows the payment date (issued / part-paid). */
    invoice_fixed: boolean;
    /** Made by the customer's recurring income. */
    recurring: boolean;
    done: boolean;
};
