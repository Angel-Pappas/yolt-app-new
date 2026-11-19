<?php

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $starting_balance
 * @property int|null $fee_entity_id
 * @property int|null $fee_category_id
 * @property string|null $statement_balance
 * @property Carbon|null $statement_date
 * @property Carbon|null $concluded_until
 */
#[Fillable(['name', 'starting_balance', 'fee_entity_id', 'fee_category_id'])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starting_balance' => 'decimal:2',
            'statement_balance' => 'decimal:2',
            'statement_date' => 'date',
            'concluded_until' => 'date',
        ];
    }

    /** Whether bank fees can be added for this wallet (it knows where they go). */
    public function takesBankFees(): bool
    {
        return $this->fee_entity_id !== null && $this->fee_category_id !== null;
    }
}
