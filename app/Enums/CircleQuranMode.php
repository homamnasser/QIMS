<?php

namespace App\Enums;

enum CircleQuranMode: string
{
    case Recitation = 'recitation';
    case Talqin = 'talqin';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Recitation => 'تسميع',
            self::Talqin => 'تلقين',
            self::None => 'دروس عامة بلا قرآن',
        };
    }
}
