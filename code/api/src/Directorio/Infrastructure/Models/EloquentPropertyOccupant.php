<?php

declare(strict_types=1);

namespace Urbania\Directorio\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Shared\Infrastructure\Concerns\HasUuidV7;

class EloquentPropertyOccupant extends Model
{
    use HasUuidV7;
    use SoftDeletes;

    protected $table = 'property_occupants';

    protected $fillable = [
        'id',
        'contact_id',
        'property_id',
        'occupant_type_id',
        'es_principal',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<EloquentContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(EloquentContact::class, 'contact_id');
    }

    /**
     * @return BelongsTo<EloquentProperty, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(EloquentProperty::class, 'property_id');
    }

    /**
     * @return BelongsTo<EloquentOccupantType, $this>
     */
    public function occupantType(): BelongsTo
    {
        return $this->belongsTo(EloquentOccupantType::class, 'occupant_type_id');
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
