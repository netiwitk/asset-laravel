<?php

namespace App\Enums;

use App\Models\Loan;
use App\Models\User;

/**
 * Who may press each loan button, and in which status. The Filament loan table
 * and the scanner API both ask here, so the two can never disagree.
 */
enum LoanAction: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case HandOver = 'hand_over';
    case ReceiveReturn = 'receive_return';
    case Cancel = 'cancel';

    public function allows(User $user, Loan $loan): bool
    {
        return match ($this) {
            self::Approve, self::Reject => $user->isOfficer() && $loan->status === LoanStatus::Pending,
            self::HandOver => $user->isOfficer() && $loan->status === LoanStatus::Approved,
            self::ReceiveReturn => $user->isOfficer() && $loan->status === LoanStatus::HandedOver,
            self::Cancel => in_array($loan->status, [LoanStatus::Pending, LoanStatus::Approved], true)
                && ($user->isOfficer() || $loan->requester_id === $user->id),
        };
    }
}
