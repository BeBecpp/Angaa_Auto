<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkOrderStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case Diagnosing = 'diagnosing';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case WaitingParts = 'waiting_parts';
    case QualityCheck = 'quality_check';
    case ReadyForPickup = 'ready_for_pickup';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Хүлээн авсан',
            self::Diagnosing => 'Онош тавьж байна',
            self::AwaitingApproval => 'Үнийн санал зөвшөөрөл хүлээж буй',
            self::Approved => 'Зөвшөөрсөн',
            self::InProgress => 'Засвар хийгдэж байна',
            self::WaitingParts => 'Сэлбэг хүлээж буй',
            self::QualityCheck => 'Чанарын шалгалт',
            self::ReadyForPickup => 'Авахад бэлэн',
            self::Delivered => 'Олгосон',
            self::Cancelled => 'Цуцалсан',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received, self::Diagnosing => 'info',
            self::AwaitingApproval, self::WaitingParts => 'warning',
            self::Approved, self::InProgress, self::QualityCheck => 'primary',
            self::ReadyForPickup, self::Delivered => 'success',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Explicit forward transitions. Waiting parts, cancellation and reopen are handled
     * by dedicated rules in TransitionWorkOrder because they need a reason.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Received => [self::Diagnosing, self::Cancelled],
            self::Diagnosing => [self::AwaitingApproval, self::Cancelled],
            self::AwaitingApproval => [self::Approved, self::Diagnosing, self::Cancelled],
            self::Approved => [self::InProgress, self::Cancelled],
            self::InProgress => [self::QualityCheck, self::WaitingParts, self::AwaitingApproval],
            self::WaitingParts => [],
            self::QualityCheck => [self::ReadyForPickup, self::InProgress],
            self::ReadyForPickup => [self::Delivered, self::InProgress],
            self::Delivered => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Delivered, self::Cancelled], true);
    }

    /**
     * Statuses where repair work may be performed on approved scope.
     */
    public function allowsRepairWork(): bool
    {
        return in_array($this, [self::Approved, self::InProgress], true);
    }
}
