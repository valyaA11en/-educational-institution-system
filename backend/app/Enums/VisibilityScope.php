<?php

namespace App\Enums;

enum VisibilityScope: string
{
    case ALL = 'all';
    case GROUP = 'group';
    case SUBGROUP = 'subgroup';
    case INDIVIDUAL = 'individual';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'Все',
            self::GROUP => 'Группа',
            self::SUBGROUP => 'Подгруппа',
            self::INDIVIDUAL => 'Индивидуально',
        };
    }
}


