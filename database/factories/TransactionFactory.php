<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'invoice_date' => now()->toDateString(),
            'description' => fake()->sentence(3),
            'type' => 'expense',
            'net' => fake()->randomFloat(2, 10, 1000),
            'vat_amount' => 0,
            'withheld_amount' => 0,
            'wallet_id' => Wallet::factory(),
            'is_reconciled' => false,
            'invoice_not_required' => false,
        ];
    }
}
