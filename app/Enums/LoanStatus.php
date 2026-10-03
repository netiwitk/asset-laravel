<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum LoanStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case HandedOver = 'handed_over';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'รออนุมัติ',
            self::Approved => 'อนุมัติแล้ว',
            self::Rejected => 'ไม่อนุมัติ',
            self::HandedOver => 'ส่งมอบแล้ว',
            self::Returned => 'คืนแล้ว',
            self::Cancelled => 'ยกเลิก',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::HandedOver => 'primary',
            self::Returned => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Approved => Heroicon::OutlinedCheckBadge,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::HandedOver => Heroicon::OutlinedHandRaised,
            self::Returned => Heroicon::OutlinedArrowUturnLeft,
            self::Cancelled => Heroicon::OutlinedMinusCircle,
        };
    }
}
