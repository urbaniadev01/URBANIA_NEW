<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EloquentContact extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'contacts';

    protected $fillable = [
        'id',
        'user_id',
        'nombre',
        'email',
        'telefono',
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
}
