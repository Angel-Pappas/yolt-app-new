<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Entity;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Models\WithheldTaxRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        $withheld20 = WithheldTaxRate::create(['name' => '20%', 'rate' => 20]);

        $expense = collect(['Software', 'Marketing', 'Office', 'Bank Expenses', 'Fuel', 'Payroll'])
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

        $this->recurrences($bank, $suppliers, $customers, $expense, $income, $vat24->id, $withheld20->id);

        // One-offs: two still open, one done (reconciled).
        foreach ([
            ['New laptop', 1450, $expense['Office'], -20, true],
            ['Trade fair stand', 2300, $expense['Marketing'], 5, false],
            ['Office chairs', 640, $expense['Office'], 12, false],
        ] as [$description, $net, $category, $days, $reconciled]) {
            $date = Carbon::now()->addDays($days)->toDateString();
            Transaction::create([
                'date' => $date,
                'invoice_date' => $date,
                'description' => $description,
                'type' => 'expense',
                'net' => $net,
                'vat_amount' => round($net * 0.24, 2),
                'withheld_amount' => 0,
                'vat_rate_id' => $vat24->id,
                'entity_id' => $suppliers->random()->id,
                'category_id' => $category->id,
                'wallet_id' => $bank->id,
                'is_reconciled' => $reconciled,
                'is_one_off' => true,
                'invoice_not_required' => false,
            ]);
        }
    }

    /**
     * A few recurrences in every group of the Recurring page: payroll, contractors,
     * expenses (one ended, one with a raise to come) and income.
     *
     * @param  Collection<int, Entity>  $suppliers
     * @param  Collection<int, Entity>  $customers
     * @param  Collection<string, Category>  $expense
     * @param  Collection<string, Category>  $income
     */
    private function recurrences(Wallet $bank, Collection $suppliers, Collection $customers, Collection $expense, Collection $income, int $vat24, int $withheld20): void
    {
        $month = Carbon::now()->startOfMonth();

        // Payroll: [employee name, start, net, FMY, EFKA employee, EFKA employer].
        $salaries = [
            ['Μαρία Παπαδοπούλου', $month->copy()->subYear(), 1450, 96.4, 201.69, 323.06],
            ['Nikos Georgiou', $month->copy()->subMonths(5), 1100, 41.2, 153.01, 245.08],
        ];
        foreach ($salaries as [$name, $start, $net, $fmy, $efkaEmployee, $efkaEmployer]) {
            $employee = Entity::create(['name' => $name, 'type' => 'employee']);
            $recurrence = $this->recurrence($employee, $bank, 'expense', $expense['Payroll'], 'Salary', true, $start, null);
            $recurrence->entries()->create([
                'start_date' => $start,
                'end_date' => null,
                'net' => $net,
                'fmy_amount' => $fmy,
                'efka_employee_amount' => $efkaEmployee,
                'efka_employer_amount' => $efkaEmployer,
                'amount_mode' => 'net',
                'position' => 0,
            ]);
        }

        $studio = Entity::create(['name' => 'Studio Kappa', 'type' => 'contractor']);
        $devLabs = Entity::create(['name' => 'Dev Labs IKE', 'type' => 'contractor']);

        // Line-based: [entity, type, category, description, periods of [start, end, net, VAT, withheld]].
        $plans = [
            [$studio, 'expense', $expense['Marketing'], 'Design retainer', [
                [$month->copy()->subMonths(8), null, 800, $vat24, $withheld20],
            ]],
            [$devLabs, 'expense', $expense['Software'], 'Maintenance', [
                [$month->copy()->subMonths(3), $month->copy()->addMonths(9)->endOfMonth(), 1500, $vat24, $withheld20],
            ]],
            [$suppliers[0], 'expense', $expense['Software'], 'Workspace', [
                [$month->copy()->subYear(), $month->copy()->addMonth()->endOfMonth(), 54, $vat24, null],
                [$month->copy()->addMonths(2), null, 64, $vat24, null],
            ]],
            [$suppliers[1], 'expense', $expense['Software'], 'Hosting', [
                [$month->copy()->subMonths(6), null, 120.5, $vat24, null],
            ]],
            [$suppliers[5], 'expense', $expense['Office'], 'Old server', [
                [$month->copy()->subYear(), $month->copy()->subMonths(2)->endOfMonth(), 14, $vat24, null],
            ]],
            [$customers[2], 'income', $income['Services Sold'], 'Support contract', [
                [$month->copy()->subMonths(4), null, 2500, $vat24, null],
            ]],
        ];
        foreach ($plans as [$entity, $type, $category, $description, $periods]) {
            $recurrence = $this->recurrence($entity, $bank, $type, $category, $description, false, $periods[0][0], $periods[array_key_last($periods)][1]);
            foreach ($periods as $i => [$start, $end, $amount, $vat, $withheld]) {
                $entry = $recurrence->entries()->create([
                    'start_date' => $start,
                    'end_date' => $end,
                    'net' => 0,
                    'amount_mode' => 'net',
                    'position' => $i,
                ]);
                $entry->lines()->create([
                    'amount' => $amount,
                    'vat_rate_id' => $vat,
                    'withheld_rate_id' => $withheld,
                    'position' => 0,
                ]);
            }
        }
    }

    /** A monthly recurrence on the 1st, paid from the bank. */
    private function recurrence(Entity $entity, Wallet $bank, string $type, Category $category, string $description, bool $payroll, Carbon $start, ?Carbon $end): Recurrence
    {
        return Recurrence::create([
            'entity_id' => $entity->id,
            'type' => $type,
            'description' => $description,
            'category_id' => $category->id,
            'wallet_id' => $bank->id,
            'is_payroll' => $payroll,
            'interval_count' => 1,
            'interval_unit' => 'month',
            'day_of_month' => 1,
            'start_date' => $start,
            'end_date' => $end,
            'active' => true,
        ]);
    }
}
