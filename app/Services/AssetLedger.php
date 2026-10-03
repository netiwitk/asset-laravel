<?php

namespace App\Services;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Enums\LoanStatus;
use App\Enums\MovementType;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Loan;
use App\Models\RepairOrder;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only way an asset's condition, availability or department changes.
 * Every change updates the asset AND appends an asset_movements row in one transaction,
 * so the ledger always explains the current state.
 *
 * Rule violations throw DomainException with a message safe to show the user.
 */
class AssetLedger
{
    public static function register(array $attributes, int $departmentId, User $actor, ?string $note = null): Asset
    {
        return DB::transaction(function () use ($attributes, $departmentId, $actor, $note) {
            $asset = new Asset($attributes);
            $asset->forceFill([
                'department_id' => $departmentId,
                'condition' => Condition::Usable,
                'availability' => Availability::Available,
            ])->save();

            AssetMovement::create([
                'asset_id' => $asset->id,
                'actor_id' => $actor->id,
                'type' => MovementType::OpeningBalance,
                'to_condition' => $asset->condition,
                'to_availability' => $asset->availability,
                'to_department_id' => $departmentId,
                'occurred_at' => now(),
                'note' => $note,
            ]);

            return $asset;
        });
    }

    public static function approve(Loan $loan, User $actor): void
    {
        self::requireLoanStatus($loan, LoanStatus::Pending);
        self::requireLendable($loan->asset);

        try {
            self::transitionLoan($loan, [LoanStatus::Pending], ['status' => LoanStatus::Approved, 'approver_id' => $actor->id, 'approved_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            // loans_one_active_per_asset_unique: the database is the real guard against double booking.
            throw new DomainException('ทรัพย์สินนี้มีคำขอยืมที่อนุมัติหรือส่งมอบแล้ว');
        }
    }

    public static function reject(Loan $loan, User $actor): void
    {
        self::requireLoanStatus($loan, LoanStatus::Pending);
        self::transitionLoan($loan, [LoanStatus::Pending], ['status' => LoanStatus::Rejected, 'approver_id' => $actor->id, 'approved_at' => now()]);
    }

    public static function cancel(Loan $loan): void
    {
        self::requireLoanStatus($loan, LoanStatus::Pending, LoanStatus::Approved);
        self::transitionLoan($loan, [LoanStatus::Pending, LoanStatus::Approved], ['status' => LoanStatus::Cancelled]);
    }

    /** The asset becomes on_loan at hand-over, not at approval. */
    public static function handOver(Loan $loan, User $actor): void
    {
        self::requireLoanStatus($loan, LoanStatus::Approved);
        $asset = $loan->asset;
        self::requireLendable($asset);

        DB::transaction(function () use ($loan, $asset, $actor) {
            self::transitionLoan($loan, [LoanStatus::Approved], ['status' => LoanStatus::HandedOver, 'handed_over_at' => now()]);
            self::move($asset, $actor, MovementType::Borrow, ['availability' => Availability::OnLoan], ['loan_id' => $loan->id]);
        });
    }

    public static function receiveReturn(Loan $loan, User $actor, Condition $condition, ?string $note = null): void
    {
        self::requireLoanStatus($loan, LoanStatus::HandedOver);
        if ($condition === Condition::Disposed) {
            throw new DomainException('การคืนบันทึกสภาพได้แค่ ใช้งานได้ หรือ ชำรุด');
        }

        DB::transaction(function () use ($loan, $actor, $condition, $note) {
            self::transitionLoan($loan, [LoanStatus::HandedOver], ['status' => LoanStatus::Returned, 'returned_on' => today()]);
            self::move($loan->asset, $actor, MovementType::Return,
                ['availability' => Availability::Available, 'condition' => $condition],
                ['loan_id' => $loan->id], $note);
        });
    }

    public static function sendToRepair(Asset $asset, User $actor, ?string $vendor, ?string $expectedReturnOn, ?string $note = null): RepairOrder
    {
        self::requireAvailableAndNotDisposed($asset);

        return DB::transaction(function () use ($asset, $actor, $vendor, $expectedReturnOn, $note) {
            $order = RepairOrder::create([
                'asset_id' => $asset->id,
                'requested_by' => $actor->id,
                'vendor' => $vendor,
                'sent_on' => today(),
                'expected_return_on' => $expectedReturnOn,
            ]);
            self::move($asset, $actor, MovementType::SendRepair, ['availability' => Availability::InRepair], ['repair_order_id' => $order->id], $note);

            return $order;
        });
    }

    public static function receiveFromRepair(Asset $asset, User $actor, Condition $condition, ?string $cost = null, ?string $note = null): void
    {
        if ($asset->availability !== Availability::InRepair) {
            throw new DomainException('ทรัพย์สินนี้ไม่ได้อยู่ระหว่างซ่อม');
        }
        if ($condition === Condition::Disposed) {
            throw new DomainException('ถ้าซ่อมไม่ได้ ให้รับคืนเป็น ชำรุด แล้วจึงจำหน่าย');
        }

        DB::transaction(function () use ($asset, $actor, $condition, $cost, $note) {
            $order = $asset->repairOrders()->whereNull('finished_on')->latest('id')->first();
            $order?->update(['finished_on' => today(), 'cost' => $cost, 'result_note' => $note]);
            self::move($asset, $actor, MovementType::ReceiveRepair,
                ['availability' => Availability::Available, 'condition' => $condition],
                ['repair_order_id' => $order?->id], $note);
        });
    }

    public static function transfer(Asset $asset, User $actor, int $departmentId, ?string $note = null): void
    {
        self::requireAvailableAndNotDisposed($asset);
        if ($asset->department_id === $departmentId) {
            throw new DomainException('ทรัพย์สินอยู่ที่หน่วยงานนี้อยู่แล้ว');
        }

        DB::transaction(fn () => self::move($asset, $actor, MovementType::Transfer, ['department_id' => $departmentId], [], $note));
    }

    public static function dispose(Asset $asset, User $actor, ?string $note = null): void
    {
        self::requireAvailableAndNotDisposed($asset);

        DB::transaction(fn () => self::move($asset, $actor, MovementType::Dispose, ['condition' => Condition::Disposed], [], $note));
    }

    /**
     * Conditional UPDATE: it only matches if the row still has the state we read.
     * If someone else changed the asset in between, zero rows match and nothing is written.
     */
    private static function move(Asset $asset, User $actor, MovementType $type, array $to, array $refs = [], ?string $note = null): AssetMovement
    {
        $from = [
            'condition' => $asset->condition,
            'availability' => $asset->availability,
            'department_id' => $asset->department_id,
        ];
        $to += $from;

        // withTrashed: a loan or repair that is already open must still be closable after a soft delete.
        $matched = Asset::withTrashed()
            ->whereKey($asset->id)
            ->where('condition', $from['condition']->value)
            ->where('availability', $from['availability']->value)
            ->where('department_id', $from['department_id'])
            ->update([
                'condition' => $to['condition']->value,
                'availability' => $to['availability']->value,
                'department_id' => $to['department_id'],
                'updated_at' => now(),
            ]);

        if ($matched === 0) {
            throw new DomainException('สถานะทรัพย์สินถูกเปลี่ยนโดยผู้ใช้อื่นแล้ว กรุณาโหลดหน้าใหม่');
        }

        $asset->forceFill($to)->syncOriginal();

        return AssetMovement::create([
            'asset_id' => $asset->id,
            'actor_id' => $actor->id,
            'type' => $type,
            'from_condition' => $from['condition'],
            'to_condition' => $to['condition'],
            'from_availability' => $from['availability'],
            'to_availability' => $to['availability'],
            'from_department_id' => $from['department_id'],
            'to_department_id' => $to['department_id'],
            'occurred_at' => now(),
            'note' => $note,
            ...$refs,
        ]);
    }

    /**
     * Compare-and-set on the loan row, like move() does for assets: the UPDATE only matches
     * while the status is still one we allow, so two people clicking at once cannot overwrite each other.
     *
     * @param  array<LoanStatus>  $from
     * @param  array<string, mixed>  $changes
     */
    private static function transitionLoan(Loan $loan, array $from, array $changes): void
    {
        $matched = Loan::whereKey($loan->id)
            ->whereIn('status', $from)
            ->update([...$changes, 'updated_at' => now()]);

        if ($matched === 0) {
            throw new DomainException('คำขอนี้ถูกเปลี่ยนสถานะโดยผู้ใช้อื่นแล้ว กรุณาโหลดหน้าใหม่');
        }

        $loan->forceFill($changes)->syncOriginal();
    }

    private static function requireLendable(Asset $asset): void
    {
        if ($asset->trashed()) {
            throw new DomainException('ทรัพย์สินนี้ถูกลบออกจากทะเบียนแล้ว');
        }
        if ($asset->condition !== Condition::Usable || $asset->availability !== Availability::Available) {
            throw new DomainException('ทรัพย์สินต้องใช้งานได้และว่างอยู่ (ตอนนี้: '.$asset->condition->getLabel().' · '.$asset->availability->getLabel().')');
        }
    }

    private static function requireLoanStatus(Loan $loan, LoanStatus ...$allowed): void
    {
        if (! in_array($loan->status, $allowed, true)) {
            throw new DomainException('คำขอนี้อยู่ในสถานะ "'.$loan->status->getLabel().'" ทำรายการนี้ไม่ได้');
        }
    }

    private static function requireAvailableAndNotDisposed(Asset $asset): void
    {
        if ($asset->trashed()) {
            throw new DomainException('ทรัพย์สินนี้ถูกลบออกจากทะเบียนแล้ว');
        }
        if ($asset->condition === Condition::Disposed) {
            throw new DomainException('ทรัพย์สินนี้ถูกจำหน่ายแล้ว');
        }
        if ($asset->availability !== Availability::Available) {
            throw new DomainException('ทรัพย์สินต้องอยู่ในสถานะ ว่าง ก่อน (ตอนนี้: '.$asset->availability->getLabel().')');
        }
        if ($asset->hasApprovedLoan()) {
            throw new DomainException('ทรัพย์สินนี้มีคำขอยืมที่อนุมัติแล้ว ต้องยกเลิกคำขอก่อน');
        }
    }
}
