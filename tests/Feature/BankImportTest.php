<?php

use App\Models\BankLine;
use App\Models\Category;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Wallet;
use App\Support\AlphaStatement;
use App\Support\BankComparison;
use App\Support\BankReconciler;
use App\Support\BankText;
use App\Support\WalletBalances;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16');
});

/**
 * An Alpha Bank CSV export in the bank's own shape (BOM, header lines, `="…"`
 * cells, newest first). Lines: [Α/Α, date, text, reference, amount, Π|Χ].
 *
 * @param  list<array{0: int, 1: string, 2: string, 3: string, 4: string, 5: string}>  $lines
 */
function alphaCsv(array $lines, string $balance = '2915,70 Π'): string
{
    $out = "\xEF\xBB\xBFΤίτλος;Κινήσεις Λογαριασμού: GR00TEST\nΗμερομηνία ; 16/11/2026\nΝέο Μικτό Υπόλοιπο:; {$balance};\nΠροηγούμενο Μικτό Υπόλοιπο:; 100,00 Π;\n\n";
    $out .= "Α/Α;Ημερομηνία;Αιτιολογία;Κατάστημα;Τοκισμός από;Αρ. συναλλαγής;Ποσό;Πρόσημο ποσού;\n";
    foreach ($lines as [$n, $date, $text, $ref, $amount, $sign]) {
        $out .= "{$n};{$date};=\"{$text}\";99;{$date};=\"{$ref}\";{$amount};{$sign};\n";
    }

    return $out;
}

function bankLine(Wallet $wallet, string $date, float $amount, string $text = 'SOMETHING', int $position = 1): BankLine
{
    return BankLine::query()->create([
        'wallet_id' => $wallet->id,
        'date' => $date,
        'position' => $position,
        'text' => $text,
        'amount' => $amount,
        'reference' => '',
        'fingerprint' => uniqid('', true),
    ]);
}

function feeWallet(): Wallet
{
    $bank = Entity::factory()->create(['name' => 'Alpha Bank', 'type' => 'supplier']);
    $fees = Category::factory()->create(['name' => 'Bank Expenses', 'type' => 'expense']);

    return Wallet::factory()->create(['name' => 'Alpha Bank', 'starting_balance' => 0, 'fee_entity_id' => $bank->id, 'fee_category_id' => $fees->id]);
}

test('an Alpha Bank CSV is read in bank order with signs and the closing balance', function () {
    $statement = AlphaStatement::parse(alphaCsv([
        [1, '12/11/2026', 'ΕΞΟΔΑ ΕΝΤΟΛΗΣ', 'R2', '4,00', 'Χ'],
        [2, '12/11/2026', 'ΕΝΤ.261112949027Ξ044', 'R2', '1000,00', 'Π'],
        [3, '12/11/2026', 'FΑCΕΒΚ *WRJΖΝΕ6ΕV4', 'R1', '124,00', 'Χ'],
        [4, '01/10/2026', 'ΧΟΡ989003070526587', 'R0', '25.003,78', 'Χ'],
    ]));

    expect($statement['balance'])->toBe(2915.70)
        ->and($statement['balance_date'])->toBe('2026-11-16')
        ->and(array_column($statement['lines'], 'date'))->toBe(['2026-10-01', '2026-11-12', '2026-11-12', '2026-11-12'])
        // Within a day the earliest (highest Α/Α) comes first.
        ->and(array_column($statement['lines'], 'text'))->toBe(['ΧΟΡ989003070526587', 'FΑCΕΒΚ *WRJΖΝΕ6ΕV4', 'ΕΝΤ.261112949027Ξ044', 'ΕΞΟΔΑ ΕΝΤΟΛΗΣ'])
        ->and(array_column($statement['lines'], 'position'))->toBe([1, 1, 2, 3])
        ->and(array_column($statement['lines'], 'amount'))->toBe([-25003.78, -124.0, 1000.0, -4.0]);
    // A fee and its payment share a reference but are separate lines.
    expect(count(array_unique(array_column($statement['lines'], 'fingerprint'))))->toBe(4);
});

test('a file that is not an Alpha Bank statement is refused', function () {
    AlphaStatement::parse("Date,Amount\n2026-11-01,10\n");
})->throws(InvalidArgumentException::class);

test('bank text folds look-alike Greek letters, accents and changing codes', function () {
    expect(BankText::key('FΑCΕΒΚ *WRJΖΝΕ6ΕV4'))->toBe('FACEBK')
        ->and(BankText::key('FACEBK *CPDCMC6EV4'))->toBe('FACEBK')
        ->and(BankText::key('ΑΝΤΗRΟΡΙC* CLΑUDΕ SU'))->toBe('ANTHROPIC CLAUDE SU')
        ->and(BankText::key('ΧΟΡ989003070526587'))->toBe('')
        ->and(BankText::nameScore('Κώνστας Γιώργος', 'ΚΩΝΣΤΑΣ'))->toBe(1)
        ->and(BankText::nameScore('One Password', '1ΡΑSSWΟRD'))->toBe(1)
        ->and(BankText::nameScore('Meta', 'FΑCΕΒΚ *X'))->toBe(0)
        ->and(BankText::isBankCharge('ΕΞΟΔΑ ΙΝSΤΑΝΤ ΤRΑΝSF'))->toBeTrue()
        ->and(BankText::isBankCharge('ΕΞΔ.ΤΗΡ.ΛΟΓ.11/26'))->toBeTrue()
        ->and(BankText::isBankCharge('ΕΝΤΟΛΗ ΙΝSΤΑΝΤ ΤRΑΝS'))->toBeFalse();
});

test('uploading a statement stores its lines once and its balance', function () {
    $user = User::factory()->create();
    $wallet = feeWallet();
    $csv = alphaCsv([
        [2, '12/11/2026', 'ΕΞΟΔΑ ΕΝΤΟΛΗΣ', 'R2', '4,00', 'Χ'],
        [1, '10/11/2026', 'SΤRΙΡΕ-Ζ.ΑΙ', 'R1', '131,97', 'Χ'],
    ]);

    $this->actingAs($user)->post('/bank-import', ['wallet_id' => $wallet->id, 'file' => UploadedFile::fake()->createWithContent('t.csv', $csv)])
        ->assertRedirect('/bank-import?wallet='.$wallet->id);
    $this->actingAs($user)->post('/bank-import', ['wallet_id' => $wallet->id, 'file' => UploadedFile::fake()->createWithContent('t.csv', $csv)]);

    expect(BankLine::query()->count())->toBe(2);
    $wallet->refresh();
    expect((float) $wallet->statement_balance)->toBe(2915.70)
        ->and($wallet->statement_date->toDateString())->toBe('2026-11-16');
});

test('an upload that is not a statement shows an error', function () {
    $this->actingAs(User::factory()->create())
        ->post('/bank-import', ['wallet_id' => feeWallet()->id, 'file' => UploadedFile::fake()->createWithContent('x.csv', 'nope')])
        ->assertSessionHasErrors('file');
    expect(BankLine::query()->count())->toBe(0);
});

test('a line with the same amount as an open row suggests confirming it, which reconciles it on the bank day', function () {
    $wallet = feeWallet();
    $row = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-10-29', 'invoice_date' => '2026-10-29', 'net' => 1300, 'vat_amount' => 0]);
    $line = bankLine($wallet, '2026-10-30', -1300, 'ΕΝΤΟΛΗ ΙΝSΤΑΝΤ ΤRΑΝS', 7);

    $s = BankReconciler::suggestions($wallet, collect([$line]));
    expect($s[$line->id]['proposal'])->toBe(['kind' => 'match', 'transaction_id' => $row->id]);

    $this->actingAs(User::factory()->create())->post("/bank-lines/{$line->id}/accept", ['transaction_ids' => [$row->id]])->assertRedirect();

    $row->refresh();
    expect($row->is_reconciled)->toBeTrue()
        ->and($row->date->toDateString())->toBe('2026-10-30')
        // The invoice date followed the date, as it was still equal.
        ->and($row->invoice_date->toDateString())->toBe('2026-10-30')
        ->and($row->bank_position)->toBe(7)
        ->and($row->bank_line_id)->toBe($line->id);
});

test('a row whose entity the bank names is suggested at a new amount, and takes the bank amount', function () {
    $wallet = feeWallet();
    $vat = VatRate::factory()->create(['rate' => 24]);
    $konstas = Entity::factory()->create(['name' => 'Κώνστας Γιώργος', 'type' => 'contractor']);
    $row = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'entity_id' => $konstas->id, 'date' => '2026-10-30', 'invoice_date' => '2026-10-15', 'net' => 1849.35, 'vat_amount' => 443.84, 'vat_rate_id' => $vat->id]);
    $row->vatLines()->create(['net' => 1849.35, 'vat_rate_id' => $vat->id, 'vat_amount' => 443.84, 'position' => 0]);
    $line = bankLine($wallet, '2026-10-30', -2334.80, 'ΚΩΝΣΤΑΣ');

    $s = BankReconciler::suggestions($wallet, collect([$line]));
    expect($s[$line->id]['proposal'])->toBe(['kind' => 'match', 'transaction_id' => $row->id]);

    BankReconciler::link($line, $row);

    $row->refresh();
    expect(round(WalletBalances::cashTotal($row), 2))->toBe(2334.80)
        // An invoice date set apart by hand stays.
        ->and($row->invoice_date->toDateString())->toBe('2026-10-15')
        ->and(round((float) $row->vatLines()->sum('net') + (float) $row->vatLines()->sum('vat_amount'), 2))->toBe(2334.80);
});

test('a payroll row keeps its FMY and EFKA and moves its net to the bank amount', function () {
    $wallet = feeWallet();
    $payroll = Category::factory()->create(['name' => 'Payroll', 'type' => 'expense']);
    $row = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'category_id' => $payroll->id, 'date' => '2026-10-30', 'net' => 1000, 'fmy_amount' => 50, 'efka_employee_amount' => 133.70]);
    $line = bankLine($wallet, '2026-10-30', -820);

    BankReconciler::link($line, $row);

    $row->refresh();
    expect((float) $row->net)->toBe(1003.70)
        ->and((float) $row->fmy_amount)->toBe(50.0)
        ->and(round(WalletBalances::cashTotal($row), 2))->toBe(820.0);
});

test('two rows with the same amount and nothing to tell them apart get no guess', function () {
    $wallet = feeWallet();
    Transaction::factory()->count(2)->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-10-30', 'net' => 771.67]);
    $line = bankLine($wallet, '2026-10-30', -771.67, 'ΕΝΤΟΛΗ ΙΝSΤΑΝΤ ΤRΑΝS');

    $s = BankReconciler::suggestions($wallet, collect([$line]));
    expect($s[$line->id]['proposal'])->toBeNull()
        ->and($s[$line->id]['candidates'])->toHaveCount(2);
});

test('one row is never suggested for two lines', function () {
    $wallet = feeWallet();
    $row = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-10-07', 'net' => 76]);
    $first = bankLine($wallet, '2026-10-07', -76, 'FACEBK *A1', 1);
    $second = bankLine($wallet, '2026-10-19', -76, 'FACEBK *B2', 1);

    $s = BankReconciler::suggestions($wallet, collect([$second, $first]));
    expect($s[$first->id]['proposal'])->toBe(['kind' => 'match', 'transaction_id' => $row->id])
        ->and($s[$second->id]['proposal'])->toBeNull();
});

test('a row already reconciled by hand is matched, not duplicated', function () {
    $wallet = feeWallet();
    $row = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-10-05', 'net' => 18.55, 'is_reconciled' => true]);
    $line = bankLine($wallet, '2026-10-05', -18.55, 'ΟΡΕΝΑΙ *CΗΑΤGΡΤ SUΒS');

    $s = BankReconciler::suggestions($wallet, collect([$line]));
    expect($s[$line->id]['proposal'])->toBe(['kind' => 'match', 'transaction_id' => $row->id]);
});

test('the bank’s own charges are suggested and added as the wallet’s bank fee', function () {
    $wallet = feeWallet();
    $line = bankLine($wallet, '2026-10-13', -1.30, 'ΕΞΟΔΑ ΙΝSΤΑΝΤ ΤRΑΝSF', 2);

    $s = BankReconciler::suggestions($wallet, collect([$line]));
    expect($s[$line->id]['proposal'])->toBe(['kind' => 'fee']);

    $this->actingAs(User::factory()->create())->post('/bank-lines/apply', ['ids' => [$line->id]])->assertRedirect();

    $fee = $line->refresh()->transactions->sole();
    expect($fee->type)->toBe('expense')
        ->and($fee->entity_id)->toBe($wallet->fee_entity_id)
        ->and($fee->category_id)->toBe($wallet->fee_category_id)
        ->and((float) $fee->net)->toBe(1.30)
        ->and($fee->is_reconciled)->toBeTrue()
        ->and($fee->bank_position)->toBe(2);
});

test('a bank text seen before suggests a new row like last time; a refund becomes income from that supplier', function () {
    $wallet = feeWallet();
    $vat = VatRate::factory()->create(['rate' => 24]);
    $meta = Entity::factory()->create(['name' => 'Meta', 'type' => 'supplier']);
    $marketing = Category::factory()->create(['name' => 'Marketing', 'type' => 'expense']);
    $user = User::factory()->create();

    // Last month's line became Meta · Marketing at 24%.
    $old = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'entity_id' => $meta->id, 'category_id' => $marketing->id, 'date' => '2026-09-07', 'net' => 61.29, 'vat_amount' => 14.71, 'vat_rate_id' => $vat->id, 'is_reconciled' => true]);
    $old->vatLines()->create(['net' => 61.29, 'vat_rate_id' => $vat->id, 'vat_amount' => 14.71, 'position' => 0]);
    $old->forceFill(['bank_line_id' => bankLine($wallet, '2026-09-07', -76, 'FΑCΕΒΚ *OLD1')->id])->save();

    $line = bankLine($wallet, '2026-10-22', -124, 'FΑCΕΒΚ *ΡΥG5Υ9JDV4');
    $s = BankReconciler::suggestions($wallet, collect([$line]));
    expect($s[$line->id]['proposal'])->toBe(['kind' => 'like', 'transaction_id' => $old->id]);

    $this->actingAs($user)->post("/bank-lines/{$line->id}/like", ['transaction_id' => $old->id]);
    $new = $line->refresh()->transactions->sole();
    expect($new->entity_id)->toBe($meta->id)
        ->and($new->category_id)->toBe($marketing->id)
        ->and($new->vat_rate_id)->toBe($vat->id)
        ->and(round(WalletBalances::cashTotal($new), 2))->toBe(124.0)
        ->and($new->date->toDateString())->toBe('2026-10-22');

    // Money back from the same merchant: an income from Meta, no category.
    $refund = bankLine($wallet, '2026-10-25', 7.19, 'FΑCΕΒΚ *REFUND9');
    BankReconciler::addLike($refund, $old->load('vatLines', 'withheldLines'), $user->id);
    $income = $refund->refresh()->transactions->sole();
    expect($income->type)->toBe('income')
        ->and($income->entity_id)->toBe($meta->id)
        ->and($income->category_id)->toBeNull();
});

test('a merchant named like an entity suggests a row like that entity’s latest', function () {
    $wallet = feeWallet();
    $hetzner = Entity::factory()->create(['name' => 'Hetzner', 'type' => 'supplier']);
    $software = Category::factory()->create(['name' => 'Software', 'type' => 'expense']);
    $latest = Transaction::factory()->create(['type' => 'expense', 'entity_id' => $hetzner->id, 'category_id' => $software->id, 'date' => '2026-08-21', 'net' => 200]);
    $line = bankLine($wallet, '2026-10-21', -224.25, 'ΗΕΤΖΝΕR ΟΝLΙΝΕ GΜΒΗ');

    $s = BankReconciler::suggestions($wallet, collect([$line]));
    expect($s[$line->id]['proposal'])->toBe(['kind' => 'like', 'transaction_id' => $latest->id]);
});

test('a transaction added from a bank line confirms it', function () {
    $wallet = feeWallet();
    $line = bankLine($wallet, '2026-10-08', -31.91, 'ΑLΙΕΧΡRΕSSCΟΜ', 1);

    $this->actingAs(User::factory()->create())->post('/transactions', [
        'type' => 'expense', 'date' => '2026-10-08', 'invoice_date' => '2026-10-08', 'wallet_id' => $wallet->id,
        'amount_mode' => 'total', 'lines' => [['amount' => '31.91']], 'bank_line_id' => $line->id,
    ])->assertSessionHasNoErrors();

    $t = $line->refresh()->transactions->sole();
    expect($t->is_reconciled)->toBeTrue()
        ->and($t->bank_position)->toBe(1);
});

test('adding a payment with a bank fee adds the fee as its own expense', function () {
    $wallet = feeWallet();
    $plain = Wallet::factory()->create();
    $user = User::factory()->create();
    $payment = fn (int $walletId) => [
        'type' => 'expense', 'date' => '2026-10-13', 'invoice_date' => '2026-10-13', 'wallet_id' => $walletId,
        'amount_mode' => 'total', 'lines' => [['amount' => '832']], 'bank_fee' => '1.30',
    ];

    $this->actingAs($user)->post('/transactions', $payment($wallet->id))->assertSessionHasNoErrors();
    $fee = Transaction::query()->where('entity_id', $wallet->fee_entity_id)->sole();
    expect((float) $fee->net)->toBe(1.30)
        ->and($fee->category_id)->toBe($wallet->fee_category_id)
        ->and($fee->wallet_id)->toBe($wallet->id)
        ->and($fee->date->toDateString())->toBe('2026-10-13');

    // A wallet with nowhere for fees to go adds none.
    $this->actingAs($user)->post('/transactions', $payment($plain->id))->assertSessionHasNoErrors();
    expect(Transaction::query()->where('wallet_id', $plain->id)->count())->toBe(1);
});

test('within a day, rows follow the bank order', function () {
    $wallet = feeWallet();
    $later = Transaction::factory()->create(['wallet_id' => $wallet->id, 'date' => '2026-10-30', 'bank_position' => 2]);
    $earlier = Transaction::factory()->create(['wallet_id' => $wallet->id, 'date' => '2026-10-30', 'bank_position' => 1]);
    $unplaced = Transaction::factory()->create(['wallet_id' => $wallet->id, 'date' => '2026-10-30']);

    expect(Transaction::query()->inBankOrder()->pluck('id')->all())->toBe([$earlier->id, $later->id, $unplaced->id])
        ->and(WalletBalances::runningFor($wallet->id, 0)->pluck('id')->all())->toBe([$earlier->id, $later->id, $unplaced->id]);
});

test('deleting the transaction a line became puts the line back to do', function () {
    $wallet = feeWallet();
    $row = Transaction::factory()->create(['wallet_id' => $wallet->id, 'date' => '2026-10-30', 'net' => 10]);
    $line = bankLine($wallet, '2026-10-30', -10);
    BankReconciler::link($line, $row);
    expect(BankLine::query()->pending()->count())->toBe(0);

    $row->delete();

    expect(BankLine::query()->pending()->count())->toBe(1)
        ->and($line->refresh()->isPending())->toBeTrue();
});

test('a pair can be undone', function () {
    $wallet = feeWallet();
    $user = User::factory()->create();
    $line = bankLine($wallet, '2026-10-30', -10);

    $row = Transaction::factory()->create(['wallet_id' => $wallet->id, 'date' => '2026-10-30', 'net' => 10]);
    BankReconciler::link($line, $row);
    $this->actingAs($user)->post("/bank-lines/{$line->id}/unlink");
    expect($line->refresh()->isPending())->toBeTrue()
        ->and($row->refresh()->bank_line_id)->toBeNull()
        ->and($row->bank_position)->toBeNull()
        ->and($row->is_reconciled)->toBeTrue();
});

test('pairing by hand lets go of what either side was paired with before', function () {
    $wallet = feeWallet();
    $row = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-10-30', 'net' => 10, 'vat_amount' => 0]);
    $other = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-10-28', 'net' => 12, 'vat_amount' => 0]);
    $line = bankLine($wallet, '2026-10-30', -10, 'A', 1);
    $second = bankLine($wallet, '2026-10-30', -12, 'B', 2);
    BankReconciler::link($line, $row);
    BankReconciler::link($second, $other);
    $user = User::factory()->create();

    // Drag `other` onto the first line, whose row already adds up to it: it takes
    // that row's place, on the line's day and place, keeping its own amount.
    $this->actingAs($user)->post("/bank-lines/{$line->id}/match", ['transaction_id' => $other->id])->assertRedirect();

    expect($line->refresh()->transactions->pluck('id')->all())->toBe([$other->id])
        ->and($second->refresh()->isPending())->toBeTrue()
        ->and($row->refresh()->bank_line_id)->toBeNull()
        ->and($row->bank_position)->toBeNull()
        ->and($other->refresh()->bank_position)->toBe(1)
        ->and($other->date->toDateString())->toBe('2026-10-30')
        ->and(WalletBalances::cashTotal($other))->toBe(12.0);
});

test('the page compares the bank balance with the app’s checked balance on that day', function () {
    $wallet = feeWallet();
    $wallet->forceFill(['statement_balance' => 90, 'statement_date' => '2026-11-16'])->save();
    Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-11-10', 'net' => 10, 'is_reconciled' => true]);
    Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-11-17', 'net' => 5, 'is_reconciled' => true]);
    bankLine($wallet, '2026-11-10', -10);

    $this->actingAs(User::factory()->create())->get('/bank-import?wallet='.$wallet->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('bank-import/index')
            ->where('wallet.app_balance', -10)
            ->where('wallet.statement_balance', '90.00')
            // From the month of the earliest unpaired line to the statement's day.
            ->where('period', ['from' => '2026-11-01', 'to' => '2026-11-16'])
            ->has('rows', 1)
            ->where('rows.0.status', 'differs')
            ->where('rows.0.differences.unconfirmed', true));
});

test('guests cannot reach the bank import', function () {
    $this->get('/bank-import')->assertRedirect('/login');
});

test('a row that only shares a name is not matched a week away or at a very different amount', function () {
    $wallet = feeWallet();
    $angelos = Entity::factory()->create(['name' => 'Σπυράτος Άγγελος', 'type' => 'contractor']);
    Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'entity_id' => $angelos->id, 'date' => '2026-10-30', 'net' => 3000]);
    $dividend = bankLine($wallet, '2026-10-23', -4000, 'ΣΠΥΡΑΤΟΣ ΜΕΡΙΣΜΑ');
    $sameDay = bankLine($wallet, '2026-10-30', -9000, 'ΣΠΥΡΑΤΟΣ');

    $s = BankReconciler::suggestions($wallet, collect([$dividend, $sameDay]));
    expect($s[$dividend->id]['candidates'])->toBe([])
        ->and($s[$sameDay->id]['candidates'])->toBe([]);
});

test('next to its automatic duplicate, the row typed and reconciled by hand is the match', function () {
    $wallet = feeWallet();
    $ploi = Entity::factory()->create(['name' => 'Ploi', 'type' => 'supplier']);
    Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'entity_id' => $ploi->id, 'date' => '2026-10-07', 'net' => 30, 'source' => 'recurrence']);
    $typed = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'entity_id' => $ploi->id, 'date' => '2026-10-07', 'net' => 30, 'is_reconciled' => true]);
    $line = bankLine($wallet, '2026-10-07', -30, 'ΜΟL*ΡLΟΙ ΙΟ');

    $s = BankReconciler::suggestions($wallet, collect([$line]));
    expect($s[$line->id]['proposal'])->toBe(['kind' => 'match', 'transaction_id' => $typed->id]);
});

test('when rows with the very amount exist but none stands out, nothing new is suggested', function () {
    $wallet = feeWallet();
    $hetzner = Entity::factory()->create(['name' => 'Hetzner', 'type' => 'supplier']);
    Transaction::factory()->count(2)->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'entity_id' => $hetzner->id, 'date' => '2026-10-21', 'net' => 50]);
    $line = bankLine($wallet, '2026-10-21', -50, 'ΗΕΤΖΝΕR ΟΝLΙΝΕ GΜΒΗ');

    $s = BankReconciler::suggestions($wallet, collect([$line]));
    expect($s[$line->id]['proposal'])->toBeNull();
});

test('the bank and the app side by side: match, differs, missing from either side, in bank order', function () {
    $wallet = feeWallet();
    $row = fn (string $date, float $net, array $more = []) => Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => $date, 'invoice_date' => $date, 'net' => $net, 'vat_amount' => 0, ...$more]);

    $paired = $row('2026-10-05', 10);
    $pairedLine = bankLine($wallet, '2026-10-05', -10, 'PAIRED', 2);
    BankReconciler::link($pairedLine, $paired);
    $early = $row('2026-10-03', 20);
    $guessLine = bankLine($wallet, '2026-10-05', -20, 'GUESS', 1);
    $missingLine = bankLine($wallet, '2026-10-07', -30, 'MISSING');
    $appOnly = $row('2026-10-05', 40, ['description' => 'Never paid']);
    $row('2026-10-09', 50, ['is_excluded' => true]);
    $row('2026-11-02', 60);

    $rows = BankComparison::rows($wallet, '2026-10-01', '2026-10-31');

    expect(array_map(fn (array $r): array => [$r['status'], $r['line']?->id, $r['transaction']?->id], $rows))->toBe([
        ['differs', $guessLine->id, $early->id],
        ['match', $pairedLine->id, $paired->id],
        ['app_only', null, $appOnly->id],
        ['bank_only', $missingLine->id, null],
    ])
        ->and($rows[0]['differences'])->toBe(['date' => '2026-10-03', 'unconfirmed' => true])
        ->and($rows[0]['linked'])->toBeFalse()
        ->and($rows[1]['differences'])->toBe([]);
});

test('a paired row edited since shows what differs from the bank', function () {
    $wallet = feeWallet();
    $t = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-10-05', 'net' => 10, 'vat_amount' => 0]);
    $line = bankLine($wallet, '2026-10-05', -10);
    BankReconciler::link($line, $t);
    $t->update(['net' => 11, 'is_reconciled' => false]);

    expect(BankComparison::differences($line->refresh(), [$t->refresh()], true))->toBe(['amount' => 11.0, 'unreconciled' => true]);
});

test('an upload pairs same-day same-amount rows and adds the bank fees by itself, and nothing else', function () {
    $user = User::factory()->create();
    $wallet = feeWallet();
    $sameDay = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-11-10', 'net' => 131.97, 'vat_amount' => 0]);
    $twoDaysOff = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-11-09', 'net' => 50, 'vat_amount' => 0]);
    $csv = alphaCsv([
        [3, '12/11/2026', 'ΕΞΟΔΑ ΕΝΤΟΛΗΣ', 'R2', '4,00', 'Χ'],
        [2, '11/11/2026', 'ΗΕΤΖΝΕR', 'R3', '50,00', 'Χ'],
        [1, '10/11/2026', 'SΤRΙΡΕ-Ζ.ΑΙ', 'R1', '131,97', 'Χ'],
    ]);

    $this->actingAs($user)->post('/bank-import', ['wallet_id' => $wallet->id, 'file' => UploadedFile::fake()->createWithContent('t.csv', $csv)]);

    expect($sameDay->refresh()->bankLine?->text)->toBe('SΤRΙΡΕ-Ζ.ΑΙ')
        ->and($sameDay->is_reconciled)->toBeTrue()
        ->and($twoDaysOff->refresh()->bank_line_id)->toBeNull()
        ->and($twoDaysOff->is_reconciled)->toBeFalse()
        ->and(Transaction::query()->where('entity_id', $wallet->fee_entity_id)->where('net', 4)->where('is_reconciled', true)->exists())->toBeTrue();
});

/**
 * Alpha books a credit-line repayment and its cost as one line (−25.003,78); the app
 * has the €25.000 transfer and the €3,78 fee.
 *
 * @return array{0: Wallet, 1: Transaction, 2: Transaction}
 */
function repaymentAndCost(string $feeDate = '2026-10-01'): array
{
    $wallet = feeWallet();
    $credit = Wallet::factory()->create(['name' => 'Αλληλόχρεος']);
    $transfer = Transaction::factory()->create(['type' => 'transfer', 'wallet_id' => $wallet->id, 'to_wallet_id' => $credit->id, 'date' => '2026-10-01', 'net' => 25000, 'vat_amount' => 0, 'is_reconciled' => true, 'entity_id' => null, 'category_id' => null]);
    $cost = Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'entity_id' => $wallet->fee_entity_id, 'category_id' => $wallet->fee_category_id, 'date' => $feeDate, 'net' => 3.78, 'vat_amount' => 0, 'is_reconciled' => true]);

    return [$wallet, $transfer, $cost];
}

test('one bank line that is several rows on its day is suggested, settled on upload and shown as one pair', function () {
    [$wallet, $transfer, $cost] = repaymentAndCost();
    $csv = alphaCsv([[1, '01/10/2026', 'ΧΟΡ989003070526587', 'R1', '25.003,78', 'Χ']]);

    $line = bankLine($wallet, '2026-10-01', -25003.78, 'ΧΟΡ989003070526587');
    expect(BankReconciler::suggestions($wallet, collect([$line]))[$line->id]['proposal'])
        ->toBe(['kind' => 'group', 'transaction_ids' => [$transfer->id, $cost->id]]);
    $line->delete();

    $this->actingAs(User::factory()->create())->post('/bank-import', ['wallet_id' => $wallet->id, 'file' => UploadedFile::fake()->createWithContent('t.csv', $csv)]);

    $line = BankLine::query()->sole();
    expect($line->transactions->pluck('id')->all())->toBe([$transfer->id, $cost->id])
        // Each keeps its own amount: together they are the line.
        ->and((float) $transfer->refresh()->net)->toBe(25000.0)
        ->and((float) $cost->refresh()->net)->toBe(3.78);

    $rows = BankComparison::rows($wallet, '2026-10-01', '2026-10-31');
    expect(array_map(fn (array $r): array => [$r['status'], $r['line']?->id, $r['part_of']?->id, $r['transaction']?->id], $rows))->toBe([
        ['match', $line->id, null, $transfer->id],
        ['match', null, $line->id, $cost->id],
    ]);
});

test('rows that add up to a line a few days away are suggested but not settled by themselves', function () {
    [$wallet, $transfer, $cost] = repaymentAndCost('2026-10-02');
    $line = bankLine($wallet, '2026-10-01', -25003.78, 'ΧΟΡ1');

    expect(BankReconciler::settle($wallet, null))->toBe(0);
    $rows = BankComparison::rows($wallet, '2026-10-01', '2026-10-31');
    expect($rows[0]['status'])->toBe('differs')
        ->and($rows[1]['differences'])->toBe(['date' => '2026-10-02']);

    $this->actingAs(User::factory()->create())->post("/bank-lines/{$line->id}/accept", ['transaction_ids' => [$transfer->id, $cost->id]]);
    expect($cost->refresh()->date->toDateString())->toBe('2026-10-01')
        ->and($cost->bank_line_id)->toBe($line->id)
        ->and((float) $transfer->refresh()->net)->toBe(25000.0);
});

test('two different combinations adding up to the line get no guess', function () {
    $wallet = feeWallet();
    foreach ([10, 20, 15, 15] as $net) {
        Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'date' => '2026-10-01', 'net' => $net, 'vat_amount' => 0]);
    }
    $line = bankLine($wallet, '2026-10-01', -30, 'ΧΟΡ2');

    expect(BankReconciler::suggestions($wallet, collect([$line]))[$line->id]['proposal'])->toBeNull();
});

test('rows paired by hand join a line until they add up to it; one can be taken out again', function () {
    [$wallet, $transfer, $cost] = repaymentAndCost();
    $line = bankLine($wallet, '2026-10-01', -25003.78, 'ΧΟΡ3');
    $user = User::factory()->create();

    $this->actingAs($user)->post("/bank-lines/{$line->id}/match", ['transaction_id' => $transfer->id]);
    $rows = BankComparison::rows($wallet, '2026-10-01', '2026-10-31');
    expect($rows[0]['status'])->toBe('differs')
        ->and($rows[0]['differences'])->toBe(['amount' => 25000.0]);

    $this->actingAs($user)->post("/bank-lines/{$line->id}/match", ['transaction_id' => $cost->id]);
    expect($line->refresh()->transactions->pluck('id')->all())->toBe([$transfer->id, $cost->id])
        ->and(BankComparison::rows($wallet, '2026-10-01', '2026-10-31')[0]['status'])->toBe('match');

    $this->actingAs($user)->post("/bank-lines/{$line->id}/unlink", ['transaction_id' => $cost->id]);
    expect($line->refresh()->transactions->pluck('id')->all())->toBe([$transfer->id])
        ->and($cost->refresh()->bank_line_id)->toBeNull();
});

test('a row added from a bank line with its bank fee makes the line together with the fee', function () {
    $wallet = feeWallet();
    $credit = Wallet::factory()->create(['name' => 'Αλληλόχρεος']);
    $line = bankLine($wallet, '2026-10-01', -25003.78, 'ΧΟΡ4');

    $this->actingAs(User::factory()->create())->post('/transactions', [
        'type' => 'transfer', 'date' => '2026-10-01', 'invoice_date' => '2026-10-01', 'wallet_id' => $wallet->id,
        'to_wallet_id' => $credit->id, 'net' => '25000', 'bank_line_id' => $line->id, 'bank_fee' => '3.78',
    ])->assertSessionHasNoErrors();

    $rows = $line->refresh()->transactions;
    expect($rows)->toHaveCount(2)
        ->and((float) $rows[0]->net)->toBe(25000.0)
        ->and($rows[1]->entity_id)->toBe($wallet->fee_entity_id)
        ->and($line->pairedCash())->toBe(25003.78);
});
