<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Availability: string implements HasColor, HasLabel
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
}
