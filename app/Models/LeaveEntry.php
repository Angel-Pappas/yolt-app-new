<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A stretch of annual leave an employee took.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $entity_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $days
 * @property string|null $note
 */
#[Fillable(['start_date', 'end_date', 'days', 'note'])]
class LeaveEntry extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:1',
        ];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
