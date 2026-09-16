<?php

namespace App\Models;

use App\Support\IncomeTaxLedger;
use Database\Factories\IncomeTaxYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One fiscal year's income tax and its installment schedule. Drives
 * {@see IncomeTaxLedger}.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $year
 * @property string $revenue_before_tax
 * @property string $total_tax
 * @property string $first_installment_month
 * @property string $last_installment_month
 * @property string $monthly_installment_amount
 */
#[Fillable([
    'year',
    'revenue_before_tax',
    'total_tax',
    'first_installment_month',
    'last_installment_month',
    'monthly_installment_amount',
])]
class IncomeTaxYear extends Model
{
    /** @use HasFactory<IncomeTaxYearFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'revenue_before_tax' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'first_installment_month' => 'date:Y-m-d',
            'last_installment_month' => 'date:Y-m-d',
            'monthly_installment_amount' => 'decimal:2',
        ];
    }
}
