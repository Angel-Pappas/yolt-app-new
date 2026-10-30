<?php

use App\Models\DeMinimisAid;
use App\Models\User;
use App\Support\DeMinimisExport;
use OpenSpout\Reader\XLSX\Reader;

/** The five grants the data migration starts the list with. */
const DE_MINIMIS_MIGRATED = 5;

test('the list starts with the grants from the 2026-10-30 declaration', function () {
    $aids = DeMinimisAid::query()->orderBy('decision_date')->orderBy('id')->get();

    expect($aids)->toHaveCount(DE_MINIMIS_MIGRATED);
    expect((float) $aids->sum('approved_amount'))->toBe(35000.0);
    expect((float) $aids->sum('paid_amount'))->toBe(35000.0);
    expect($aids->first()->decision_number)->toBe('ACC-10115035-NCSRD-6002637');
    expect($aids->first()->approved_amount)->toBe('21700.00');
    expect($aids->first()->last_payment_date->format('Y-m-d'))->toBe('2023-08-11');
    expect($aids->pluck('beneficiary_vat_number')->unique()->all())->toBe(['801487298']);
});

test('an active user can view the de minimis list', function () {
    $this->actingAs(User::factory()->create())
        ->get('/de-minimis')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('de-minimis/index')
            ->has('aids', DE_MINIMIS_MIGRATED));
});

test('a guest cannot view or download the list', function () {
    $this->get('/de-minimis')->assertRedirect(route('login'));
    $this->get('/de-minimis/download.md')->assertRedirect(route('login'));
    $this->get('/de-minimis/download.xlsx')->assertRedirect(route('login'));
    $this->post('/de-minimis', [])->assertRedirect(route('login'));
});

test('an active user can add a grant', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/de-minimis', [
        'programme' => 'Philoxenia',
        'decision_number' => 'ASDA-1',
        'decision_date' => '2026-11-15',
        'approved_amount' => '1234.5',
        'paid_amount' => null,
        'last_payment_date' => null,
        'beneficiary_name' => 'YOLT LABS Ι Κ Ε',
        'beneficiary_vat_number' => '801487298',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $aid = DeMinimisAid::where('decision_number', 'ASDA-1')->firstOrFail();
    expect($aid->approved_amount)->toBe('1234.50');
    expect($aid->paid_amount)->toBeNull();
    expect($aid->decision_date->format('Y-m-d'))->toBe('2026-11-15');
    expect($aid->user_id)->toBe($user->id);
});

test('a grant needs its programme, decision, date, approved amount and beneficiary', function () {
    $this->actingAs(User::factory()->create())
        ->post('/de-minimis', ['approved_amount' => '-5', 'decision_date' => 'nope'])
        ->assertSessionHasErrors([
            'programme', 'decision_number', 'decision_date', 'approved_amount',
            'beneficiary_name', 'beneficiary_vat_number',
        ]);
});

test('an active user can edit and soft-delete a grant', function () {
    $user = User::factory()->create();
    $aid = DeMinimisAid::factory()->create();

    $this->actingAs($user)->patch("/de-minimis/{$aid->id}", [
        ...$aid->only(['programme', 'decision_number', 'beneficiary_name', 'beneficiary_vat_number']),
        'decision_date' => '2025-01-02',
        'approved_amount' => '900',
        'paid_amount' => '450',
        'last_payment_date' => '2025-03-04',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($aid->fresh()->paid_amount)->toBe('450.00');

    $this->actingAs($user)->delete("/de-minimis/{$aid->id}")->assertRedirect();

    expect(DeMinimisAid::find($aid->id))->toBeNull();
    expect(DeMinimisAid::withTrashed()->find($aid->id))->not->toBeNull();
});

test('the markdown download is the official table with a total row', function () {
    DeMinimisAid::query()->forceDelete();
    DeMinimisAid::factory()->create([
        'programme' => 'Later | grant',
        'decision_number' => 'B',
        'decision_date' => '2024-02-01',
        'approved_amount' => '1500',
        'paid_amount' => null,
        'last_payment_date' => null,
    ]);
    DeMinimisAid::factory()->create([
        'programme' => 'Earlier grant',
        'decision_number' => 'A',
        'decision_date' => '2023-05-22',
        'approved_amount' => '800.5',
        'paid_amount' => '800.5',
        'last_payment_date' => '2023-06-20',
        'beneficiary_name' => 'YOLT LABS Ι Κ Ε',
        'beneficiary_vat_number' => '801487298',
    ]);

    $response = $this->actingAs(User::factory()->create())->get('/de-minimis/download.md');

    $response->assertOk()->assertDownload('de-minimis-'.now()->format('Y-m-d').'.md');
    expect($response->headers->get('Content-Type'))->toStartWith('text/markdown');

    $lines = explode("\n", trim($response->getContent()));
    expect($lines[2])->toBe('| '.implode(' | ', DeMinimisExport::HEADINGS).' |');
    // Oldest decision first; a `|` in a value is escaped; Greek money and dates.
    expect($lines[4])->toBe('| 1 | Earlier grant | A | 22/05/2023 | 800,50 € | 800,50 € | 20/06/2023 | YOLT LABS Ι Κ Ε | 801487298 |');
    expect($lines[5])->toStartWith('| 2 | Later \| grant | B | 01/02/2024 | 1.500,00 € |  |  |');
    expect($lines[6])->toBe('|  | **Σύνολο** |  |  | **2.300,50 €** | **800,50 €** |  |  |  |');
    expect($lines)->toHaveCount(7);
});

test('the deleted grants are left out of the downloads', function () {
    DeMinimisAid::query()->first()->delete();

    $md = $this->actingAs(User::factory()->create())->get('/de-minimis/download.md')->getContent();

    expect(substr_count($md, "\n"))->toBe(2 + 2 + DE_MINIMIS_MIGRATED - 1 + 1);
});

test('the excel download holds real numbers and dates with a total row', function () {
    $response = $this->actingAs(User::factory()->create())->get('/de-minimis/download.xlsx');

    $response->assertOk()->assertDownload('de-minimis-'.now()->format('Y-m-d').'.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'dm-test');
    file_put_contents($path, $response->streamedContent());

    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    unlink($path);

    expect($rows)->toHaveCount(1 + DE_MINIMIS_MIGRATED + 1);
    expect($rows[0])->toBe(DeMinimisExport::HEADINGS);
    expect($rows[1][2])->toBe('ACC-10115035-NCSRD-6002637');
    expect($rows[1][3])->toBeInstanceOf(DateTimeImmutable::class);
    expect($rows[1][3]->format('Y-m-d'))->toBe('2023-04-05');
    expect((float) $rows[1][4])->toBe(21700.0);
    $total = end($rows);
    expect($total[1])->toBe(DeMinimisExport::TOTAL_LABEL);
    expect((float) $total[4])->toBe(35000.0);
    expect((float) $total[5])->toBe(35000.0);
});
