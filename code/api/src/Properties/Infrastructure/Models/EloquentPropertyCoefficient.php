<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentPropertyCoefficient extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'property_coefficients';

    protected $fillable = [
        'id',
        'property_id',
        'tipo',
        'valor',
        'vigente_desde',
        'vigente_hasta',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:4',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    /**
     * @return BelongsTo<EloquentProperty, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(EloquentProperty::class, 'property_id');
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

    /**
     * Check if this coefficient is currently active (no end date).
     */
    public function isActive(): bool
    {
        return $this->vigente_hasta === null;
    }
}
