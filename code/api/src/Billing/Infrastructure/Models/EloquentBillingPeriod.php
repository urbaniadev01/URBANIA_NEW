<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentBillingPeriod extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'billing_periods';

    protected $fillable = [
        'id',
        'condominium_id',
        'anio',
        'mes',
        'estado',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
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
     * @return HasMany<EloquentBillingRun, $this>
     */
    public function billingRuns(): HasMany
    {
        return $this->hasMany(EloquentBillingRun::class, 'billing_period_id');
    }

    /**
     * @return HasMany<EloquentInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(EloquentInvoice::class, 'billing_period_id');
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
