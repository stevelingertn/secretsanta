<?php

namespace App\Services\Results;

use App\Models\AwardTiebreak;
use App\Models\Car;
use App\Models\Category;

/** One award line in a results calculation. */
final class AwardOutcome
{
    public const WINNER = 'winner';
    public const TIE_PENDING = 'tie_pending';
    /** Class outcome depends on an unresolved Best Overall tie. */
    public const WAITING = 'waiting';
    public const NO_VOTES = 'no_votes';
    public const NO_ELIGIBLE = 'no_eligible';

    /**
     * @param  list<array{car: Car, votes: int}>  $candidates  tied cars (for ties) or the winner
     * @param  list<array{car: Car, votes: int}>  $ranking  eligible cars in vote order
     */
    public function __construct(
        public readonly string $scopeKey,
        public readonly string $title,
        public readonly ?Category $category,
        public readonly string $status,
        public readonly ?Car $car = null,
        public readonly int $contestantVotes = 0,
        public readonly int $systemVotes = 0,
        public readonly array $candidates = [],
        public readonly array $ranking = [],
        public readonly ?AwardTiebreak $tiebreak = null,
        public readonly ?string $explanation = null,
    ) {}

    public function isPending(): bool
    {
        return in_array($this->status, [self::TIE_PENDING, self::WAITING], true);
    }

    public function canResolveTie(): bool
    {
        return $this->status === self::TIE_PENDING;
    }
}
