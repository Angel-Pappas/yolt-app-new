<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One saved action on an open statement: what was done (`label`, for the Undo
 * button) and the whole set of line decisions after it (JSON, line id → decision).
 * Undo deletes the latest step; the one before it is the state again.
 *
 * @property int $id
 * @property int $bank_statement_id
 * @property string $label
 * @property array<string, mixed> $decisions
 */
#[Fillable(['bank_statement_id', 'user_id', 'label', 'decisions'])]
class BankStatementStep extends Model
{
    protected function casts(): array
    {
        return ['decisions' => 'array'];
    }
}
