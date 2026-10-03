<?php

namespace App\Filament\Widgets;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Enums\LoanStatus;
use App\Enums\MovementType;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Loans\LoanResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssetStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** The numbers only change on ledger actions; no need to re-query every 5 seconds. */
    protected ?string $pollingInterval = null;

    protected int|array|null $columns = ['default' => 2, 'lg' => 3];

    protected function getStats(): array
    {
        // One pass over assets with conditional sums; scoped to the user's department for staff.
        $assets = AssetResource::getEloquentQuery()->toBase()->selectRaw('
            SUM(CASE WHEN "condition" <> ? THEN 1 ELSE 0 END) AS in_register,
            SUM(CASE WHEN "condition" <> ? THEN cost ELSE 0 END) AS total_cost,
            SUM(CASE WHEN "condition" = ? AND availability = ? THEN 1 ELSE 0 END) AS ready,
            SUM(CASE WHEN availability = ? THEN 1 ELSE 0 END) AS on_loan,
            SUM(CASE WHEN availability = ? THEN 1 ELSE 0 END) AS in_repair,
            SUM(CASE WHEN "condition" = ? AND availability = ? THEN 1 ELSE 0 END) AS waiting_repair
        ', [
            Condition::Disposed->value, Condition::Disposed->value,
            Condition::Usable->value, Availability::Available->value,
            Availability::OnLoan->value, Availability::InRepair->value,
            Condition::Damaged->value, Availability::Available->value,
        ])->first();

        $loans = LoanResource::getEloquentQuery();
        $pending = (clone $loans)->where('status', LoanStatus::Pending)->count();
        $overdue = (clone $loans)->overdue()->count();

        $borrows = LoansPerWeekChart::weeklyCounts(MovementType::Borrow);

        return [
            $this->stat('ทรัพย์สินในทะเบียน', $assets->in_register, 'primary', Heroicon::OutlinedCube)
                ->description('มูลค่ารวม ฿'.number_format((float) $assets->total_cost)),
            $this->stat('พร้อมให้ยืม', $assets->ready, 'success', Heroicon::OutlinedCheckCircle)
                ->description('ใช้งานได้และว่างอยู่'),
            $this->stat('ถูกยืมอยู่', $assets->on_loan, 'primary', Heroicon::OutlinedHandRaised)
                ->description('ยืมออก '.array_sum($borrows).' ครั้งใน 8 สัปดาห์')
                ->chart(array_values($borrows)),
            $this->stat('เกินกำหนดคืน', $overdue, $overdue > 0 ? 'danger' : 'success', Heroicon::OutlinedExclamationTriangle)
                ->description($overdue > 0 ? 'ต้องติดตามให้คืน' : 'ไม่มีรายการค้างคืน')
                ->url(LoanResource::getUrl()),
            $this->stat('กำลังซ่อม', $assets->in_repair, 'warning', Heroicon::OutlinedWrenchScrewdriver)
                ->description('ชำรุดรอส่งซ่อมอีก '.number_format((int) $assets->waiting_repair).' รายการ')
                ->chart(array_values(LoansPerWeekChart::weeklyCounts(MovementType::SendRepair))),
            $this->stat('คำขอรออนุมัติ', $pending, 'accent', Heroicon::OutlinedClipboardDocumentList)
                ->description('คำขอยืมที่ยังไม่ได้พิจารณา')
                ->url(LoanResource::getUrl()),
        ];
    }

    /**
     * The asset-tone-* class lets the theme tint the icon and top bar to match the card.
     */
    private function stat(string $label, int|string|null $value, string $tone, Heroicon $icon): Stat
    {
        return Stat::make($label, number_format((int) $value))
            ->color($tone)
            ->icon($icon)
            ->extraAttributes(['class' => "asset-tone-{$tone}"]);
    }
}
