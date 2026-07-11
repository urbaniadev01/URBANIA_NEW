<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentBillingRun extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'billing_runs';

    protected $fillable = [
        'id',
        'billing_period_id',
        'ejecutado_por',
        'fecha',
        'estado',
        'resumen',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'resumen' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EloquentBillingPeriod, $this>
     */
    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(EloquentBillingPeriod::class, 'billing_period_id');
    }

    /**
     * @return HasMany<EloquentInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(EloquentInvoice::class, 'billing_run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ejecutadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutado_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
