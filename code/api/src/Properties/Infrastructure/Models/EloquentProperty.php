<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Directorio\Infrastructure\Models\EloquentPropertyOccupant;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentProperty extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'properties';

    protected $fillable = [
        'id',
        'condominium_id',
        'tower_id',
        'property_type_id',
        'property_status_id',
        'codigo',
        'piso',
        'area_m2',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area_m2' => 'float',
            'piso' => 'integer',
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
     * @return BelongsTo<EloquentTower, $this>
     */
    public function tower(): BelongsTo
    {
        return $this->belongsTo(EloquentTower::class, 'tower_id');
    }

    /**
     * @return BelongsTo<EloquentPropertyType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EloquentPropertyType::class, 'property_type_id');
    }

    /**
     * @return BelongsTo<EloquentPropertyStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(EloquentPropertyStatus::class, 'property_status_id');
    }

    /**
     * @return HasMany<EloquentPropertyCoefficient, $this>
     */
    public function coefficients(): HasMany
    {
        return $this->hasMany(EloquentPropertyCoefficient::class, 'property_id');
    }

    /**
     * @return HasMany<EloquentPropertyOccupant, $this>
     */
    public function occupants(): HasMany
    {
        return $this->hasMany(EloquentPropertyOccupant::class, 'property_id');
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
