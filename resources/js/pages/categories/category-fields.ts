import { type CrudField } from '@/components/crud/crud-form-dialog';

/**
 * The fields of the category add/edit form — the single source of truth shared by the
 * Categories page and the transaction form's "+ Add category" button. The category's
 * `type` (income/expense) is supplied as a fixed value by each caller, not a field.
 */
export const categoryFields: CrudField[] = [
    { key: 'name', label: 'Name', type: 'text', required: true },
    {
        key: 'description',
        label: 'Description',
        type: 'textarea',
        placeholder: 'Optional — what this category is for',
    },
];
