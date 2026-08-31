<?php

namespace App\Models;

use Database\Factories\LeadFactory;
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
 * @property int|null $origin_id
 * @property int|null $status_id
 * @property string|null $website
 * @property string|null $contact_name
 * @property string|null $contact_position
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $contact_landline
 * @property string|null $description
 * @property string|null $next_step
 */
#[Fillable([
    'name',
    'origin_id',
    'status_id',
    'website',
    'contact_name',
    'contact_position',
    'contact_email',
    'contact_phone',
    'contact_landline',
    'description',
    'next_step',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<LeadOrigin, $this> */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(LeadOrigin::class);
    }

    /** @return BelongsTo<LeadStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class);
    }

    /** @return HasMany<LeadAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(LeadAction::class);
    }

    /** @return HasMany<LeadContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(LeadContact::class);
    }
}
