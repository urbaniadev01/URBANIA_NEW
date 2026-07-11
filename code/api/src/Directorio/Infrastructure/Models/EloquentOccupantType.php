<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Shared\Infrastructure\Concerns\BelongsToOrganization;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentOccupantType extends Model
{
    use BelongsToOrganization;
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'occupant_types';

    protected $fillable = [
        'id',
        'organization_id',
        'nombre',
        'descripcion',
        'created_by',
        'updated_by',
    ];

    /**
     * @return HasMany<EloquentPropertyOccupant, $this>
     */
    public function occupations(): HasMany
    {
        return $this->hasMany(EloquentPropertyOccupant::class, 'occupant_type_id');
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
