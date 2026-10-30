<?php

namespace App\Models;

use Database\Factories\DeMinimisAidFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One de minimis grant: an approval decision (or contract) and what was paid on it.
 * Listed on Finance ▸ De minimis and exported by `App\Support\DeMinimisExport`.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $programme
 * @property string $decision_number
 * @property Carbon $decision_date
 * @property string $approved_amount
 * @property string|null $paid_amount
 * @property Carbon|null $last_payment_date
 * @property string $beneficiary_name
 * @property string $beneficiary_vat_number
 */
#[Fillable([
    'programme',
    'decision_number',
    'decision_date',
    'approved_amount',
    'paid_amount',
    'last_payment_date',
    'beneficiary_name',
    'beneficiary_vat_number',
])]
class DeMinimisAid extends Model
{
    /** @use HasFactory<DeMinimisAidFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'decision_date' => 'date:Y-m-d',
            'last_payment_date' => 'date:Y-m-d',
            'approved_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }
}
