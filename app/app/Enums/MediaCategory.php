<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaCategory: string implements HasLabel
{
    case Request = 'request';
    case Intake = 'intake';
    case Before = 'before';
    case During = 'during';
    case After = 'after';

    public function getLabel(): string
    {
        return match ($this) {
            self::Request => 'Хүсэлтийн зураг',
            self::Intake => 'Хүлээн авах үеийн',
            self::Before => 'Засварын өмнө',
            self::During => 'Засварын явцад',
            self::After => 'Засварын дараа',
        };
    }
}
