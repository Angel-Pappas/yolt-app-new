<?php

namespace App\Models;

use Database\Factories\ProjectActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $project_id
 * @property string $action_date
 * @property string $body
 * @property string|null $author_name
 */
#[Fillable(['action_date', 'body', 'author_name'])]
class ProjectAction extends Model
{
    /** @use HasFactory<ProjectActionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'action_date' => 'date',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
