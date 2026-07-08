<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EloquentRefreshToken extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'refresh_tokens';

    protected $fillable = [
        'id',
        'user_id',
        'jti',
        'estado',
        'expires_at',
    ];

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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
