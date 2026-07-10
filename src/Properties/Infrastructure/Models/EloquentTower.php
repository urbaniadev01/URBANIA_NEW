<?php

declare(strict_types=1);

namespace Urbania\Properties\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentTower extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'towers';

    protected $fillable = [
        'id',
        'condominium_id',
        'nombre',
        'created_by',
        'updated_by',
    ];

    /**
     * @return BelongsTo<EloquentCondominium, $this>
     */
    public function condominium(): BelongsTo
    {
        return $this->belongsTo(EloquentCondominium::class, 'condominium_id');
    }

    /**
     * @return HasMany<EloquentProperty, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(EloquentProperty::class, 'tower_id');
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
