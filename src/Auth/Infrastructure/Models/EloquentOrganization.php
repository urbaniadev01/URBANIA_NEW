<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EloquentOrganization extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'organizations';

    protected $fillable = [
        'id',
        'nombre',
    ];

    public function uniqueIds(): array
    {
        return ['id'];
    }
}
