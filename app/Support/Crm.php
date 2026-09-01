<?php

namespace App\Support;

use App\Models\User;

/**
 * Shared helpers for the CRM activity logs (lead + project History).
 */
class Crm
{
    /**
     * Resolve who an action is attributed to. The author is the acting user,
     * unless an admin explicitly picked someone else (non-admins are always
     * themselves). `author_name` is denormalized because a colleague's name can't
     * be joined under the CRM's shared-read model.
     *
     * @return array{0: int, 1: string} [user_id, author_name]
     */
    public static function resolveActor(User $actor, ?int $pickedId): array
    {
        if ($actor->is_admin && $pickedId !== null) {
            $picked = User::query()->find($pickedId);
            if ($picked !== null) {
                return [$picked->id, $picked->name];
            }
        }

        return [$actor->id, $actor->name];
    }

    /**
     * The users an admin may attribute an action to (active users). Empty for
     * non-admins — they can only be themselves, so the picker is hidden.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function usersForPicker(User $actor): array
    {
        if (! $actor->is_admin) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }
}
