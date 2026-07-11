<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentInvoiceItem extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'invoice_items';

    protected $fillable = [
        'id',
        'invoice_id',
        'charge_concept_id',
        'descripcion',
        'valor',
        'base_calculo',
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
            'base_calculo' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<EloquentInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(EloquentInvoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<EloquentChargeConcept, $this>
     */
    public function chargeConcept(): BelongsTo
    {
        return $this->belongsTo(EloquentChargeConcept::class, 'charge_concept_id');
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
