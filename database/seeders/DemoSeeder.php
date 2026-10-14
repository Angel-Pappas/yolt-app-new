<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Realistic-looking FAKE data for the dev checkout only, so pages can be
 * screenshotted (`pnpm run shots`) and reviewed before a change ships. Never run it
 * against the live database. Login: demo@example.com / password.
 *
 *     php artisan migrate:fresh --seed --seeder=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->admin()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        $bank = Wallet::create(['name' => 'Alpha Bank', 'starting_balance' => 12500]);
        $cash = Wallet::create(['name' => 'Cash', 'starting_balance' => 300]);
        Wallet::create(['name' => 'Revolut', 'starting_balance' => 850]);

        $vat24 = VatRate::create(['name' => '24%', 'rate' => 24]);
        VatRate::create(['name' => '13%', 'rate' => 13]);
        VatRate::create(['name' => '6%', 'rate' => 6]);
        $vat0 = VatRate::create(['name' => '0%', 'rate' => 0]);
        WithheldTaxRate::create(['name' => '20%', 'rate' => 20]);

        $expense = collect(['Software', 'Marketing', 'Office', 'Bank Expenses', 'Fuel'])
            ->mapWithKeys(fn ($n) => [$n => Category::create(['name' => $n, 'type' => 'expense'])]);
        $income = collect(['Services Sold', 'Credits', 'Grant'])
            ->mapWithKeys(fn ($n) => [$n => Category::create(['name' => $n, 'type' => 'income'])]);

        $suppliers = collect(['Google', 'AWS', 'Meta', 'One Password', 'Fireflies', 'Ploi'])
            ->map(fn ($n) => Entity::create(['name' => $n, 'type' => 'supplier']));
        $customers = collect(['Dagmar-Koutsoumpidis', 'Γεωργαλάς Κωνσταντίνος', 'Acme Hellas'])
            ->map(fn ($n) => Entity::create(['name' => $n, 'type' => 'customer']));
        Entity::create(['name' => 'Alpha Bank', 'type' => 'supplier']);

        mt_srand(42);
        $start = Carbon::now()->startOfMonth()->subMonths(2);
        $end = Carbon::now()->endOfMonth();

        for ($day = $start->copy(); $day->lte($end); $day->addDays(mt_rand(1, 3))) {
            $isIncome = mt_rand(1, 5) === 1;
            $net = $isIncome ? mt_rand(500, 5000) : mt_rand(300, 40000) / 100;
            $withVat = mt_rand(0, 1) === 1;

            Transaction::create([
                'date' => $day->toDateString(),
                'invoice_date' => $day->toDateString(),
                'description' => $isIncome ? 'Invoice '.mt_rand(100, 999) : 'Monthly subscription',
                'type' => $isIncome ? 'income' : 'expense',
                'net' => $net,
                'vat_amount' => $withVat ? round($net * 0.24, 2) : 0,
                'withheld_amount' => 0,
                'vat_rate_id' => $withVat ? $vat24->id : $vat0->id,
                'entity_id' => ($isIncome ? $customers : $suppliers)->random()->id,
                'category_id' => ($isIncome ? $income : $expense)->random()->id,
                'wallet_id' => $bank->id,
                'is_reconciled' => $day->lt(Carbon::now()->startOfMonth()),
                'invoice_month' => mt_rand(0, 2) ? (int) $day->format('n') : null,
                'invoice_not_required' => false,
            ])->forceFill(['user_id' => $user->id])->save();
        }

        Transaction::create([
            'date' => $start->copy()->addDays(10)->toDateString(),
            'invoice_date' => $start->copy()->addDays(10)->toDateString(),
            'description' => 'Cash withdrawal',
            'type' => 'transfer',
            'net' => 200,
            'vat_amount' => 0,
            'withheld_amount' => 0,
            'wallet_id' => $bank->id,
            'to_wallet_id' => $cash->id,
            'is_reconciled' => true,
            'invoice_not_required' => true,
        ]);
    }
}
