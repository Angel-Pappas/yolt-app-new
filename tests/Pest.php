<?php

use App\Models\Recurrence;
use App\Models\Transaction;
use App\Support\Plan;
use App\Support\PlanItem;
use App\Support\RecurrenceGenerator;
use App\Support\WorkingDays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Write a recurrence's plan items as (unreconciled) rows, so a test can look at what
 * the plan computes for it with ordinary queries. An occurrence already paid by a
 * reconciled row is left alone, as the old generator did. Test-only: the app itself
 * never stores recurrence occurrences.
 */
function materialize(Recurrence $recurrence): void
{
    Transaction::query()->where('recurrence_id', $recurrence->id)->where('is_reconciled', false)->forceDelete();
    $paid = Transaction::query()->where('recurrence_id', $recurrence->id)->pluck('managed_key')->flip();

    foreach (RecurrenceGenerator::items($recurrence->fresh(), WorkingDays::holidaySet()) as $key => $t) {
        if ($paid->has($key)) {
            continue;
        }
        $vat = $t->vatLines->map(fn ($l) => $l->only(['net', 'vat_rate_id', 'vat_amount', 'position']))->all();
        $withheld = $t->withheldLines->map(fn ($l) => $l->only(['net', 'withheld_rate_id', 'withheld_amount', 'position']))->all();
        $t->setRelations([]);
        $t->save();
        $t->vatLines()->createMany($vat);
        $t->withheldLines()->createMany($withheld);
    }
    Plan::flush();
}

/** {@see materialize()} for every recurrence. */
function materializeAll(): void
{
    Recurrence::query()->get()->each(fn (Recurrence $r) => materialize($r));
}

/**
 * The tax payments the plan expects, by key (`tax:{tax}:{period}`) → amount.
 *
 * @return array<string, float>
 */
function taxPayments(): array
{
    Plan::flush();

    return Plan::taxItems()->filter(fn (PlanItem $i): bool => ! $i->isDone())
        ->map(fn (PlanItem $i): float => $i->open())->all();
}

/** The tax plan item for a key (or null). */
function taxPayment(string $key): ?PlanItem
{
    Plan::flush();

    return Plan::taxItems()->get($key);
}
