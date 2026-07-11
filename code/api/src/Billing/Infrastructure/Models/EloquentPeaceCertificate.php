<?php

declare(strict_types=1);

namespace Urbania\Billing\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentPeaceCertificate extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'peace_certificates';

    protected $fillable = [
        'id',
        'condominium_id',
        'property_id',
        'emitido_por',
        'numero',
        'fecha',
        'vigente_hasta',
        'pdf_url',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'vigente_hasta' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
