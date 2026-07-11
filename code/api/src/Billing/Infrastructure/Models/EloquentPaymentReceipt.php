<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentPaymentReceipt extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'payment_receipts';

    protected $fillable = [
        'id',
        'condominium_id',
        'property_id',
        'contact_id',
        'valor',
        'fecha',
        'medio',
        'referencia',
        'soporte_url',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'fecha' => 'date',
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
     * Cross-context read-only (ADR-002) — `Auth` es dueño de `Contact` (party, nunca
     * `user_id`, ADR-001 §3).
     *
     * @return BelongsTo<EloquentContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(EloquentContact::class, 'contact_id');
    }

    /**
     * @return HasMany<EloquentPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(EloquentPaymentAllocation::class, 'payment_receipt_id');
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
