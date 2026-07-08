<?php

declare(strict_types=1);

namespace Urbania\Authorization\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EloquentRole extends Model
{
    use HasUuids;

    protected $table = 'roles';

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
     * @return BelongsToMany<EloquentPermission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            EloquentPermission::class,
            'permission_role',
            'role_id',
            'permission_id',
        )->withTimestamps();
    }
}
