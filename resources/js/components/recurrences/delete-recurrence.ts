import { router } from '@inertiajs/react';

/** Delete a recurrence after a confirm — the same prompt wherever it's offered. */
export function deleteRecurrence(id: number): void {
    if (
        confirm(
            'Delete this recurring transaction? Its unreconciled generated transactions will be removed.',
        )
    ) {
        router.delete(`/recurrences/${id}`, { preserveScroll: true });
    }
}
