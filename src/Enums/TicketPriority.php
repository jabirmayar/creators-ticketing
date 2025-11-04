<?php

namespace daacreators\CreatorsTicketing\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketPriority: string implements HasColor, HasLabel
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LOW => 'info',
            self::MEDIUM => 'success',
            self::HIGH => 'danger',
        };
    }
}