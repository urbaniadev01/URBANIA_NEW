<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Urbania\Auth\Infrastructure\Models\EloquentContact;

class User extends Authenticatable
{
    use HasUuids;
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * Get the name of the column used for the password hash.
     * Override default 'password' to match our custom schema.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'organization_id',
        'email',
        'password_hash',
        'estado',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * Get the unique identifier key name for the model.
     */
    public function uniqueIds(): array
    {
        return ['id'];
    }

    /**
     * @return HasOne<EloquentContact, $this>
     */
    public function contact(): HasOne
    {
        return $this->hasOne(EloquentContact::class, 'user_id');
    }
}
