<?php

namespace App\Enums;

enum VoteSource: string
{
    case Online = 'online';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Manual => 'Manually entered',
        };
    }
}
