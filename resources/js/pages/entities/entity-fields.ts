import { type CrudField } from '@/components/crud/crud-form-dialog';

/**
 * The fields of the entity add/edit form — the single source of truth shared by the
 * Entities page and the transaction form's "+ Add entity" button, so any field added
 * here shows up in both.
 */
export const entityFields: CrudField[] = [
    { key: 'name', label: 'Name', type: 'text', required: true },
    { key: 'vat_number', label: 'VAT number (optional)', type: 'text' },
];

export const entityDescription =
    'A counterparty — a supplier, a customer, the state, and so on.';
