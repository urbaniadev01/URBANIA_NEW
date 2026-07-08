<?php

declare(strict_types=1);

namespace Urbania\Auth\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EloquentInvitation extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'invitations';

    protected $fillable = [
        'id',
        'organization_id',
        'email',
        'token',
        'estado',
        'expira_en',
    ];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['id'];
    }

    /**
     * @return BelongsTo<EloquentOrganization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(EloquentOrganization::class, 'organization_id');
    }

    public function isVigente(): bool
    {
        return $this->estado === 'vigente';
    }

    public function isExpired(): bool
    {
        return $this->expira_en->isPast();
    }
}
