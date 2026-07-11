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

class EloquentChargeConcept extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'charge_concepts';

    protected $fillable = [
        'id',
        'condominium_id',
        'nombre',
        'tipo',
        'metodo_calculo',
        'valor_base',
        'activo',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_base' => 'decimal:2',
            'activo' => 'boolean',
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
     * @return HasMany<EloquentInvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(EloquentInvoiceItem::class, 'charge_concept_id');
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
