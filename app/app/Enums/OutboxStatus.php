<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OutboxStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Dispatching = 'dispatching';
    case Simulated = 'simulated';
    case ProviderAccepted = 'provider_accepted';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Unknown = 'unknown';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Төлөвлөсөн',
            self::Queued => 'Дараалалд',
            self::Dispatching => 'Илгээж байна',
            self::Simulated => 'Туршилт — бодитоор илгээгээгүй',
            self::ProviderAccepted => 'Оператор хүлээн авсан (хүргэгдсэн нь батлагдаагүй)',
            self::Delivered => 'Хүргэгдсэн',
            self::Failed => 'Амжилтгүй',
            self::Unknown => 'Тодорхойгүй — шалгах',
            self::Skipped => 'Алгассан',
            self::Cancelled => 'Цуцалсан',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Queued => 'info',
            self::Dispatching => 'info',
            self::Simulated => 'warning',
            self::ProviderAccepted => 'info',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::Unknown => 'danger',
            self::Skipped => 'gray',
            self::Cancelled => 'gray',
        };
    }
}
