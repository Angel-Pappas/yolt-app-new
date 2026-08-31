<?php

namespace App\Models;

use Database\Factories\ProjectStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property int $position
 */
#[Fillable(['name', 'position'])]
class ProjectStatus extends Model
{
    /** @use HasFactory<ProjectStatusFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
