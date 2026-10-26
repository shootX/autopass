<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company invoice. `washes`: one wash package, paid first; its washes are credited when the admin
 * marks it paid. `discount`: the month's discount washes, billed after the service; `lines` group them
 * by package, body type and price. Every column is set by CorporateInvoices only; the buyer and seller
 * are copies made at issue time.
 */
class CorporateInvoice extends Model
{
    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const KIND_WASHES = 'washes';

    public const KIND_DISCOUNT = 'discount';

    protected $guarded = ['*'];

    protected $casts = [
        'washes' => 'integer',
        'amount' => 'decimal:2',
        'vat_rate' => 'integer',
        'buyer' => 'array',
        'seller' => 'array',
        'lines' => 'array',
        'period' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(CorporateClient::class, 'corporate_client_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CorporatePackage::class, 'corporate_package_id');
    }

    /** The discount washes this invoice billed. */
    public function discountWashes(): HasMany
    {
        return $this->hasMany(CorporateWash::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isDiscount(): bool
    {
        return $this->kind === self::KIND_DISCOUNT;
    }

    /** The package title, or the month a discount invoice bills, in the reader's language. */
    public function displayTitle(): string
    {
        return $this->isDiscount() && $this->period
            ? __('admin.inv_discount_title', ['month' => $this->period->format('m.Y')])
            : (string) $this->title;
    }

    /** The VAT inside the amount, rounded to the tetri; 0 when the seller charges none. */
    public function vatAmount(): string
    {
        $cents = (int) round((float) $this->amount * 100);
        $rate = (int) $this->vat_rate;

        return number_format($rate > 0 ? intdiv($cents * $rate * 2 + (100 + $rate), (100 + $rate) * 2) / 100 : 0, 2, '.', '');
    }
}
