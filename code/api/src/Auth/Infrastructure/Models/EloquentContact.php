<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Directorio\Infrastructure\Models\EloquentPropertyOccupant;
use Urbania\Shared\Infrastructure\Concerns\BelongsToOrganization;

class EloquentContact extends Model
{
    use BelongsToOrganization;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'contacts';

    protected $fillable = [
        'id',
        'organization_id',
        'user_id',
        'nombre',
        'email',
        'telefono',
        'created_by',
        'updated_by',
    ];

    public function uniqueIds(): array
    {
        return ['id'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<EloquentPropertyOccupant, $this>
     */
    public function occupations(): HasMany
    {
        return $this->hasMany(EloquentPropertyOccupant::class, 'contact_id');
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
