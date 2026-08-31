<?php

namespace App\Models;

use Database\Factories\LeadActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $lead_id
 * @property string $action_date
 * @property string $body
 * @property string|null $author_name
 */
#[Fillable(['action_date', 'body', 'author_name'])]
class LeadAction extends Model
{
    /** @use HasFactory<LeadActionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'action_date' => 'date',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
