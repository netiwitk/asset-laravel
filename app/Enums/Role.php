<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasColor, HasDescription, HasLabel
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

    /**
     * Shown under each choice in the user form, matching what the policies allow.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Admin => 'ทำได้ทุกอย่าง รวมถึงจัดการผู้ใช้ หน่วยงาน และหมวดหมู่',
            self::Officer => 'ลงทะเบียนทรัพย์สิน อนุมัติการยืม ส่งซ่อม และโอนย้าย',
            self::Staff => 'ดูทรัพย์สินของหน่วยงานตัวเอง และขอยืมในชื่อตัวเอง',
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
