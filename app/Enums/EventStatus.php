<?php

namespace App\Enums;

enum EventStatus: string
{
    case Setup = 'setup';
    case VotingOpen = 'voting_open';
    case VotingClosed = 'voting_closed';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Setup',
            self::VotingOpen => 'Voting open',
            self::VotingClosed => 'Voting closed, results pending',
            self::Finalized => 'Results final',
        };
    }
}
