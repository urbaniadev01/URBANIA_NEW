<?php

declare(strict_types=1);

namespace Urbania\Shared\Infrastructure\Concerns;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

trait HasUuidV7
{
    /**
     * Boot the trait — configure UUID as non-incrementing string PK.
     */
    public function initializeHasUuidV7(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    /**
     * Boot the trait — register the UUID v7 generating callback.
     */
    public static function bootHasUuidV7(): void
    {
        static::creating(function (Model $model): void {
            foreach ($model->uniqueIds() as $column) {
                if (empty($model->{$column})) {
                    $model->{$column} = Uuid::uuid7()->toString();
                }
            }
        });
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['id'];
    }
}
