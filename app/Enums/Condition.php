<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Condition: string implements HasColor, HasLabel
{
    case Usable = 'usable';
    case Damaged = 'damaged';
    case Disposed = 'disposed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Usable => 'ใช้งานได้',
            self::Damaged => 'ชำรุด',
            self::Disposed => 'จำหน่ายแล้ว',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Usable => 'success',
            self::Damaged => 'warning',
            self::Disposed => 'gray',
        };
    }
}
