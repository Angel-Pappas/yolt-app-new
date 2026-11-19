<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing a project delivers and what it is worth (net). A project's value is the
 * sum of its deliverables — no status, it's not a project tracker.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $net
 * @property int $position
 */
#[Fillable(['name', 'net', 'position'])]
class ProjectDeliverable extends Model
{
    protected function casts(): array
    {
        return ['net' => 'decimal:2', 'position' => 'integer'];
    }
}
