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
 * A piece of work for a customer (a placeholder customer when the real one isn't
 * known yet): its value is the sum of its **deliverables**. The money it brings is
 * planned on the customer, as payment lines that can cover several projects
 * together ({@see PlannedTransaction}).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $entity_id
 * @property string $name
 * @property string|null $agreed_total
 * @property string|null $notes
 * @property bool $is_excluded
 */
#[Fillable(['entity_id', 'name', 'agreed_total', 'notes', 'is_excluded'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'agreed_total' => 'decimal:2',
            'is_excluded' => 'boolean',
        ];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return HasMany<ProjectDeliverable, $this> */
    public function deliverables(): HasMany
    {
        return $this->hasMany(ProjectDeliverable::class)->orderBy('position')->orderBy('id');
    }

    /** The project's value: the sum of its deliverables (net). */
    public function totalValue(): float
    {
        return round((float) $this->deliverables->sum(fn (ProjectDeliverable $d): float => (float) $d->net), 2);
    }
}
