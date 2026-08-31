<?php

namespace App\Models;

use Database\Factories\LeadContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $lead_id
 * @property string $name
 * @property string|null $position
 * @property string|null $phone
 * @property string|null $landline
 * @property string|null $website
 * @property string|null $email
 */
#[Fillable(['name', 'position', 'phone', 'landline', 'website', 'email'])]
class LeadContact extends Model
{
    /** @use HasFactory<LeadContactFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
