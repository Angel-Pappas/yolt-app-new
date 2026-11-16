<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property Carbon $date
 * @property int|null $bank_position
 * @property int|null $bank_line_id
 * @property Carbon $invoice_date
 * @property string $description
 * @property string $type
 * @property string $net
 * @property string $vat_amount
 * @property string $withheld_amount
 * @property string|null $fmy_amount
 * @property string|null $efka_employee_amount
 * @property string|null $efka_employer_amount
 * @property int|null $entity_id
 * @property int|null $category_id
 * @property int $wallet_id
 * @property int|null $to_wallet_id
 * @property int|null $vat_rate_id
 * @property bool $is_reconciled
 * @property bool $is_one_off
 * @property bool $is_excluded
 * @property string|null $income_status
 * @property int|null $project_id
 * @property int|null $invoice_month
 * @property bool $invoice_not_required
 * @property string|null $source
 * @property int|null $recurrence_id
 * @property string|null $managed_key
 */
#[Fillable([
    'date',
    'bank_position',
    'invoice_date',
    'description',
    'type',
    'net',
    'vat_amount',
    'withheld_amount',
    'fmy_amount',
    'efka_employee_amount',
    'efka_employer_amount',
    'entity_id',
    'category_id',
    'wallet_id',
    'to_wallet_id',
    'vat_rate_id',
    'is_reconciled',
    'is_one_off',
    'is_excluded',
    'income_status',
    'project_id',
    'invoice_month',
    'invoice_not_required',
    'source',
    'recurrence_id',
    'managed_key',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    /** The hand-set tags an income can carry. Independent of reconciling. */
    public const INCOME_STATUSES = ['agreed', 'invoiced', 'paid', 'dropped'];

    /**
     * The rows that count: not switched off, and not a dropped income. Every balance,
     * projection and tax figure is computed from these only — an excluded or dropped
     * row stays in the lists but counts for nothing.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->where('is_excluded', false)
            ->where(fn ($q) => $q->whereNull('income_status')->orWhere('income_status', '!=', 'dropped'));
    }

    /** Whether this row counts (see {@see scopeCounted()}). */
    public function counts(): bool
    {
        return ! $this->is_excluded && $this->income_status !== 'dropped';
    }

    /**
     * Date order, and within a day the bank statement's order: rows a statement has
     * confirmed first, in the bank's sequence, then the rest in the order they were
     * added.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeInBankOrder(Builder $query): void
    {
        $query->orderBy('date')
            ->orderByRaw('bank_position is null')
            ->orderBy('bank_position')
            ->orderBy('id');
    }

    /**
     * Eager-load everything the shared transactions table needs to display a row and
     * seed its edit modal — the single definition of that shape, used everywhere a
     * list of transactions is rendered.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeWithListData(Builder $query): void
    {
        $query->with([
            'wallet:id,name',
            'toWallet:id,name',
            'entity:id,name',
            'category:id,name',
            'project:id,name',
            'vatLines' => fn ($q) => $q->orderBy('position')->select('id', 'transaction_id', 'net', 'vat_rate_id', 'position'),
            'withheldLines' => fn ($q) => $q->orderBy('position')->select('id', 'transaction_id', 'net', 'withheld_rate_id', 'position'),
        ]);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'invoice_date' => 'date',
            'net' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'withheld_amount' => 'decimal:2',
            'fmy_amount' => 'decimal:2',
            'efka_employee_amount' => 'decimal:2',
            'efka_employer_amount' => 'decimal:2',
            'is_reconciled' => 'boolean',
            'is_one_off' => 'boolean',
            'is_excluded' => 'boolean',
            'invoice_not_required' => 'boolean',
            'invoice_month' => 'integer',
            'bank_position' => 'integer',
        ];
    }

    /**
     * The bank statement line this row is (part of), if paired.
     *
     * @return BelongsTo<BankLine, $this>
     */
    public function bankLine(): BelongsTo
    {
        return $this->belongsTo(BankLine::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<VatRate, $this> */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Recurrence, $this> */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }

    /** @return HasMany<TransactionVatLine, $this> */
    public function vatLines(): HasMany
    {
        return $this->hasMany(TransactionVatLine::class);
    }

    /** @return HasMany<TransactionWithheldLine, $this> */
    public function withheldLines(): HasMany
    {
        return $this->hasMany(TransactionWithheldLine::class);
    }
}
