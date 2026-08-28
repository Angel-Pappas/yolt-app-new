<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $date
 * @property string $invoice_date
 * @property string $description
 * @property string $type
 * @property string $net
 * @property string $vat_amount
 * @property string $withheld_amount
 * @property int|null $entity_id
 * @property int|null $category_id
 * @property int $wallet_id
 * @property int|null $to_wallet_id
 * @property int|null $vat_rate_id
 * @property bool $is_reconciled
 * @property int|null $invoice_month
 * @property bool $invoice_not_required
 */
#[Fillable([
    'date',
    'invoice_date',
    'description',
    'type',
    'net',
    'vat_amount',
    'withheld_amount',
    'entity_id',
    'category_id',
    'wallet_id',
    'to_wallet_id',
    'vat_rate_id',
    'is_reconciled',
    'invoice_month',
    'invoice_not_required',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'invoice_date' => 'date',
            'net' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'withheld_amount' => 'decimal:2',
            'is_reconciled' => 'boolean',
            'invoice_not_required' => 'boolean',
            'invoice_month' => 'integer',
        ];
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
