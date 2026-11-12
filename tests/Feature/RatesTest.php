<?php

use App\Models\RatePeriod;
use App\Models\VatRate;
use App\Models\WithheldTaxRate;
use App\Support\AmountLines;
use App\Support\Rates;

test('a rate is its base percentage until a dated change starts', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    RatePeriod::create(['rate_type' => 'vat', 'rate_id' => $vat->id, 'valid_from' => '2027-07-01', 'rate' => 25]);
    RatePeriod::create(['rate_type' => 'vat', 'rate_id' => $vat->id, 'valid_from' => '2028-01-01', 'rate' => 23]);

    expect(Rates::vat($vat->id, '2027-06-30'))->toBe(24.0);
    expect(Rates::vat($vat->id, '2027-07-01'))->toBe(25.0);
    expect(Rates::vat($vat->id, '2027-12-31'))->toBe(25.0);
    expect(Rates::vat($vat->id, '2028-03-01'))->toBe(23.0);
});

test('an unknown or missing rate is 0', function () {
    expect(Rates::vat(null, '2027-01-01'))->toBe(0.0);
    expect(Rates::vat(999, '2027-01-01'))->toBe(0.0);
});

test('withholding rates are dated the same way and kept apart from VAT', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    $withheld = WithheldTaxRate::factory()->create(['rate' => 20]);
    RatePeriod::create(['rate_type' => 'withheld', 'rate_id' => $withheld->id, 'valid_from' => '2027-01-01', 'rate' => 15]);

    expect(Rates::withheld($withheld->id, '2027-02-01'))->toBe(15.0);
    expect(Rates::vat($vat->id, '2027-02-01'))->toBe(24.0);
});

test('amount lines resolve with the rate in force on the invoice date', function () {
    $vat = VatRate::factory()->create(['rate' => 24]);
    RatePeriod::create(['rate_type' => 'vat', 'rate_id' => $vat->id, 'valid_from' => '2027-07-01', 'rate' => 25]);
    $lines = [['amount' => '1000', 'vat_rate_id' => $vat->id]];

    expect(AmountLines::resolve($lines, 'net', '2027-06-30')['vat_amount'])->toBe(240.0);
    expect(AmountLines::resolve($lines, 'net', '2027-07-01')['vat_amount'])->toBe(250.0);
    // Total mode backs the new rate out too.
    expect(AmountLines::resolve([['amount' => '1250', 'vat_rate_id' => $vat->id]], 'total', '2027-07-01')['net'])->toBe(1000.0);
});
