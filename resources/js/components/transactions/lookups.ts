import { usePage } from '@inertiajs/react';

export type WalletOption = { id: number; name: string };
export type EntityOption = { id: number; name: string; type: string };
export type CategoryOption = { id: number; name: string; type: string };
export type ProjectOption = { id: number; name: string; entity_id: number };
export type RateOption = { id: number; name: string; rate: string };

/**
 * The finance lookup lists shared on every authenticated page (see
 * `HandleInertiaRequests`) — the single source of truth for the transaction edit
 * form's dropdowns, so it works anywhere without per-page wiring.
 */
export type FinanceLookups = {
    wallets: WalletOption[];
    entities: EntityOption[];
    categories: CategoryOption[];
    projects: ProjectOption[];
    vatRates: RateOption[];
    withheldRates: RateOption[];
};

/** Read the globally-shared finance lookups. */
export function useFinanceLookups(): FinanceLookups {
    return usePage().props.financeLookups;
}
