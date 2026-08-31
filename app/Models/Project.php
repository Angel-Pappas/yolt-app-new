<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $sort_order
 * @property string $name
 * @property int|null $lead_id
 * @property int|null $status_id
 * @property string|null $description
 * @property string|null $value
 * @property int|null $estimated_months
 * @property string|null $next_step
 */
#[Fillable([
    'name',
    'lead_id',
    'status_id',
    'description',
    'value',
    'estimated_months',
    'next_step',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'value' => 'decimal:2',
            'estimated_months' => 'integer',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<ProjectStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class);
    }

    /** @return HasMany<ProjectAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ProjectAction::class);
    }
}
