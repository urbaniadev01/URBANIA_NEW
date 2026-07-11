<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentPaymentAllocation extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'payment_allocations';

    protected $fillable = [
        'id',
        'payment_receipt_id',
        'invoice_id',
        'valor_aplicado',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_aplicado' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EloquentPaymentReceipt, $this>
     */
    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(EloquentPaymentReceipt::class, 'payment_receipt_id');
    }

    /**
     * @return BelongsTo<EloquentInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(EloquentInvoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
