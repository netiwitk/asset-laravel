<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum Availability: string implements HasColor, HasIcon, HasLabel
{
    case Available = 'available';
    case OnLoan = 'on_loan';
    case InRepair = 'in_repair';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'ว่าง',
            self::OnLoan => 'ถูกยืม',
            self::InRepair => 'ส่งซ่อม',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::OnLoan => 'info',
            self::InRepair => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Available => Heroicon::OutlinedCheck,
            self::OnLoan => Heroicon::OutlinedArrowRightCircle,
            self::InRepair => Heroicon::OutlinedWrenchScrewdriver,
        };
    }
}
