<?php

declare(strict_types=1);

namespace Urbania\Authorization\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Urbania\Authorization\Application\Services\PermissionResolver;

class EloquentRoleAssignment extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'role_assignments';

    protected $fillable = [
        'id',
        'user_id',
        'role_id',
        'scope_type',
        'scope_id',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['id'];
    }

    /**
     * Boot: invalidate the user's permission cache when an assignment is
     * created or deleted.
     */
    protected static function booted(): void
    {
        static::created(function (EloquentRoleAssignment $assignment): void {
            self::invalidateCache($assignment);
        });

        static::deleted(function (EloquentRoleAssignment $assignment): void {
            self::invalidateCache($assignment);
        });

        static::updated(function (EloquentRoleAssignment $assignment): void {
            // Only invalidate if meaningful fields changed
            if ($assignment->isDirty(['role_id', 'expires_at', 'scope_type', 'scope_id'])) {
                self::invalidateCache($assignment);
            }
        });
    }

    private static function invalidateCache(EloquentRoleAssignment $assignment): void
    {
        /** @var PermissionResolver $resolver */
        $resolver = app(PermissionResolver::class);
        $resolver->invalidateUserCache($assignment->user_id);
    }

    /**
     * @return BelongsTo<EloquentRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(EloquentRole::class, 'role_id');
    }
}
