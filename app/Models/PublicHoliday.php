<?php

namespace App\Models;

use Database\Factories\PublicHolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A public holiday date. Feeds `App\Support\WorkingDays::lastWorkingDay()` so tax
 * payment dates skip holidays as well as weekends.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $date
 * @property string|null $name
 */
#[Fillable(['date', 'name'])]
class PublicHoliday extends Model
{
    /** @use HasFactory<PublicHolidayFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }
}
