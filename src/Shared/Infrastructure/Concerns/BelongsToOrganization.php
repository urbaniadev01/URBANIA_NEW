<?php

declare(strict_types=1);

namespace Urbania\Shared\Infrastructure\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;

trait BelongsToOrganization
{
    /**
     * @return BelongsTo<EloquentOrganization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(EloquentOrganization::class, 'organization_id');
    }
}
