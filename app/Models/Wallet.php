<?php

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $starting_balance
 */
#[Fillable(['name', 'starting_balance'])]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starting_balance' => 'decimal:2',
        ];
    }
}
