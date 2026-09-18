<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Records that must never change once written (votes, ballots, tiebreaks, awards, audit rows).
 * Eloquent updates and deletes throw; the schema also has no cascading deletes.
 */
trait Immutable
{
    public static function bootImmutable(): void
    {
        static::updating(fn () => throw new LogicException(class_basename(static::class).' records are immutable.'));
        static::deleting(fn () => throw new LogicException(class_basename(static::class).' records cannot be deleted.'));
    }
}
