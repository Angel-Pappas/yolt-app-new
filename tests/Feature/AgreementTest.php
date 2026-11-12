<?php

use App\Models\Agreement;
use App\Models\Entity;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12');
    Storage::fake('local');
});

/** An agreement with dates, no file. */
function agreement(array $attributes = []): Agreement
{
    return Agreement::forceCreate(array_merge([
        'entity_id' => Entity::factory()->create(['type' => 'customer'])->id,
        'title' => 'Service agreement',
        'start_date' => '2026-01-01',
        'end_date' => null,
    ], $attributes));
}

test('an agreement is stored with its file, privately', function () {
    $customer = Entity::factory()->create(['type' => 'customer']);

    $this->actingAs(User::factory()->create())->post('/agreements', [
        'entity_id' => $customer->id,
        'title' => 'Support contract 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'file' => UploadedFile::fake()->create('contract.pdf', 300, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $a = Agreement::sole();
    expect($a->file_name)->toBe('contract.pdf');
    expect($a->file_path)->toStartWith('agreements/');
    Storage::disk('local')->assertExists($a->file_path);
});

test('an agreement can be open-ended and have no file', function () {
    $customer = Entity::factory()->create(['type' => 'customer']);

    $this->actingAs(User::factory()->create())->post('/agreements', [
        'entity_id' => $customer->id, 'title' => 'Framework', 'start_date' => '2026-01-01', 'end_date' => '',
    ])->assertSessionHasNoErrors();

    expect(Agreement::sole()->end_date)->toBeNull();
});

test('the end cannot be before the start, and odd files are refused', function () {
    $customer = Entity::factory()->create(['type' => 'customer']);

    $this->actingAs(User::factory()->create())->post('/agreements', [
        'entity_id' => $customer->id, 'title' => 'X', 'start_date' => '2026-05-01', 'end_date' => '2026-04-01',
        'file' => UploadedFile::fake()->create('run.exe', 10),
    ])->assertSessionHasErrors(['end_date', 'file']);
});

test('where an agreement stands: upcoming, active, ending, ended', function () {
    $today = Carbon::today();

    expect(agreement(['start_date' => '2027-01-01'])->state($today, 30))->toBe('upcoming');
    expect(agreement()->state($today, 30))->toBe('active');                       // open-ended
    expect(agreement(['end_date' => '2027-06-30'])->state($today, 30))->toBe('active');
    expect(agreement(['end_date' => '2026-12-12'])->state($today, 30))->toBe('ending'); // 30 days
    expect(agreement(['end_date' => '2026-12-13'])->state($today, 30))->toBe('active'); // 31 days
    expect(agreement(['end_date' => '2026-11-12'])->state($today, 30))->toBe('ending'); // ends today
    expect(agreement(['end_date' => '2026-11-11'])->state($today, 30))->toBe('ended');
});

test('the warning window is a setting', function () {
    $this->actingAs(User::factory()->create())->patch('/agreements/warning', ['warning_days' => 60])->assertRedirect();

    expect(Setting::current()->agreement_warning_days)->toBe(60);
    expect(agreement(['end_date' => '2027-01-05'])->state(Carbon::today(), Setting::current()->agreement_warning_days))->toBe('ending');
});

test('a new file replaces the old one; no file keeps it', function () {
    $user = User::factory()->create();
    $customer = Entity::factory()->create(['type' => 'customer']);
    $payload = ['entity_id' => $customer->id, 'title' => 'Contract', 'start_date' => '2026-01-01'];
    $this->actingAs($user)->post('/agreements', $payload + ['file' => UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf')]);
    $a = Agreement::sole();
    $first = $a->file_path;

    $this->actingAs($user)->post("/agreements/{$a->id}", ['title' => 'Contract (renewed)'] + $payload)->assertSessionHasNoErrors();
    expect($a->refresh()->file_path)->toBe($first);
    expect($a->title)->toBe('Contract (renewed)');

    $this->actingAs($user)->post("/agreements/{$a->id}", $payload + ['file' => UploadedFile::fake()->create('v2.pdf', 10, 'application/pdf')]);
    expect($a->refresh()->file_name)->toBe('v2.pdf');
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($a->file_path);
});

test('the file is served to signed-in users only', function () {
    $user = User::factory()->create();
    $customer = Entity::factory()->create(['type' => 'customer']);
    $this->actingAs($user)->post('/agreements', [
        'entity_id' => $customer->id, 'title' => 'Contract', 'start_date' => '2026-01-01',
        'file' => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'),
    ]);
    $a = Agreement::sole();

    $this->actingAs($user)->get("/agreements/{$a->id}/file")->assertOk();

    auth()->logout();
    $this->get("/agreements/{$a->id}/file")->assertRedirect('/login');
    $this->actingAs($user)->get('/agreements/'.agreement()->id.'/file')->assertNotFound();
});
