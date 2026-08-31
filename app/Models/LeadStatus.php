<?php

namespace App\Models;

use Database\Factories\LeadStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property int $position
 * @property bool $is_conversion
 */
#[Fillable(['name', 'position'])]
class LeadStatus extends Model
{
    /** @use HasFactory<LeadStatusFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_conversion' => 'boolean',
        ];
    }
}
