<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EloquentPasswordResetToken extends Model
{
    use HasUuids;

    protected $table = 'password_reset_tokens';

    protected $fillable = [
        'id',
        'email',
        'token_hash',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['id'];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
