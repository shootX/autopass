<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invoice for one wash package of one company. Its washes are credited when the admin marks it paid.
 * Every column is set by CorporateInvoices only; the buyer and seller are copies made at issue time.
 */
class CorporateInvoice extends Model
{
    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['*'];

    protected $casts = [
        'washes' => 'integer',
        'amount' => 'decimal:2',
        'vat_rate' => 'integer',
        'buyer' => 'array',
        'seller' => 'array',
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

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    /** The VAT inside the amount, rounded to the tetri; 0 when the seller charges none. */
    public function vatAmount(): string
    {
        $cents = (int) round((float) $this->amount * 100);
        $rate = (int) $this->vat_rate;

        return number_format($rate > 0 ? intdiv($cents * $rate * 2 + (100 + $rate), (100 + $rate) * 2) / 100 : 0, 2, '.', '');
    }
}
