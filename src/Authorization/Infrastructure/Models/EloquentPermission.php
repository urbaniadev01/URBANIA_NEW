<?php

declare(strict_types=1);

namespace Urbania\Authorization\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EloquentPermission extends Model
{
    use HasUuids;

    protected $table = 'permissions';

    protected $fillable = [
        'id',
        'name',
        'description',
    ];

    public function uniqueIds(): array
    {
        return ['id'];
    }

    /**
     * @return BelongsToMany<EloquentRole, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            EloquentRole::class,
            'permission_role',
            'permission_id',
            'role_id',
        )->withTimestamps();
    }
}
