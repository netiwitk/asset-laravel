<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Officer = 'officer';
    case Staff = 'staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'ผู้ดูแลระบบ',
            self::Officer => 'เจ้าหน้าที่พัสดุ',
            self::Staff => 'พนักงาน',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Officer => 'primary',
            self::Staff => 'gray',
        };
    }
}
