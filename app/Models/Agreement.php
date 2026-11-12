<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An agreement with an entity, kept for the record: its file and its dates.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $entity_id
 * @property string $title
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string|null $notes
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 */
#[Fillable(['entity_id', 'title', 'start_date', 'end_date', 'notes'])]
class Agreement extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'file_size' => 'integer',
        ];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Where it stands on a date: `upcoming` (not started), `active`, `ending` (ends
     * within the warning window), or `ended`. An open-ended agreement never ends.
     */
    public function state(Carbon $today, int $warningDays): string
    {
        if ($this->end_date !== null && $this->end_date->lt($today)) {
            return 'ended';
        }
        if ($this->start_date->gt($today)) {
            return 'upcoming';
        }
        if ($this->end_date !== null && $this->end_date->lte($today->copy()->addDays($warningDays))) {
            return 'ending';
        }

        return 'active';
    }
}
