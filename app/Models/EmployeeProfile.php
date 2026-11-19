<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What payroll, leave and severance need to know about an employee.
 *
 * @property int $id
 * @property int $entity_id
 * @property Carbon|null $birth_date
 * @property int $children
 * @property Carbon|null $hire_date
 * @property Carbon|null $termination_date
 * @property string $prior_service_years
 * @property int $work_days_per_week
 * @property int|null $leave_days_override
 */
#[Fillable([
    'birth_date',
    'children',
    'hire_date',
    'termination_date',
    'prior_service_years',
    'work_days_per_week',
    'leave_days_override',
])]
class EmployeeProfile extends Model
{
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'children' => 'integer',
            'prior_service_years' => 'decimal:1',
            'work_days_per_week' => 'integer',
            'leave_days_override' => 'integer',
        ];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
