<?php

declare(strict_types=1);

namespace Urbania\Mfa\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EloquentUserMfa extends Model
{
    use HasUuids;

    protected $table = 'user_mfa';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'totp_secret',
        'recovery_codes',
        'enabled_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'recovery_codes' => 'json',
            'enabled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
