<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasLabel
{
    case OpeningBalance = 'opening_balance';
    case Borrow = 'borrow';
    case Return = 'return';
    case SendRepair = 'send_repair';
    case ReceiveRepair = 'receive_repair';
    case Transfer = 'transfer';
    case Dispose = 'dispose';
    case Adjust = 'adjust';

    public function getLabel(): string
    {
        return match ($this) {
            self::OpeningBalance => 'ยกยอดเข้าระบบ',
            self::Borrow => 'ยืม',
            self::Return => 'คืน',
            self::SendRepair => 'ส่งซ่อม',
            self::ReceiveRepair => 'รับคืนจากซ่อม',
            self::Transfer => 'โอนย้ายหน่วยงาน',
            self::Dispose => 'จำหน่าย',
            self::Adjust => 'ปรับปรุงรายการ',
        };
    }
}
