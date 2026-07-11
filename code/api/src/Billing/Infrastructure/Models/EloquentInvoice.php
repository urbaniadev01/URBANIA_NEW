<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentInvoice extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'id',
        'condominium_id',
        'property_id',
        'billing_period_id',
        'billing_run_id',
        'numero',
        'fecha_emision',
        'fecha_vencimiento',
        'valor_total',
        'saldo',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'valor_total' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EloquentCondominium, $this>
     */
    public function condominium(): BelongsTo
    {
        return $this->belongsTo(EloquentCondominium::class, 'condominium_id');
    }

    /**
     * Cross-context read-only (ADR-002) — `Properties` es dueño de este modelo.
     *
     * @return BelongsTo<EloquentProperty, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(EloquentProperty::class, 'property_id');
    }

    /**
     * @return BelongsTo<EloquentBillingPeriod, $this>
     */
    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(EloquentBillingPeriod::class, 'billing_period_id');
    }

    /**
     * @return BelongsTo<EloquentBillingRun, $this>
     */
    public function billingRun(): BelongsTo
    {
        return $this->belongsTo(EloquentBillingRun::class, 'billing_run_id');
    }

    /**
     * @return HasMany<EloquentInvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(EloquentInvoiceItem::class, 'invoice_id');
    }

    /**
     * @return HasMany<EloquentPaymentAllocation, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(EloquentPaymentAllocation::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
