<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A hand change to a **computed** plan item (a recurrence occurrence or a tax
 * payment), which has no row of its own to edit: a new expected date (moved, or the
 * rest of a part-paid item) or cancelled (it won't happen).
 *
 * @property int $id
 * @property string $plan_key
 * @property Carbon|null $date
 * @property bool $cancelled
 */
#[Fillable(['plan_key', 'date', 'cancelled', 'user_id'])]
class PlanAdjustment extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date', 'cancelled' => 'boolean'];
    }
}
