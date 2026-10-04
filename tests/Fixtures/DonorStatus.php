<?php

namespace Zvizvi\FilamentColumnFilters\Tests\Fixtures;

use Filament\Support\Contracts\HasLabel;

enum DonorStatus: string implements HasLabel
{
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }
}
