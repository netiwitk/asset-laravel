<?php

namespace App\Filament\Widgets;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Enums\LoanStatus;
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

    protected int|array|null $columns = ['md' => 3, 'xl' => 5];

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

        return [
            Stat::make('ทรัพย์สินในทะเบียน', number_format((int) $assets->in_register))
                ->description('มูลค่ารวม ฿'.number_format((float) $assets->total_cost))
                ->icon(Heroicon::OutlinedCube),
            Stat::make('พร้อมให้ยืม', number_format((int) $assets->ready))
                ->description('ใช้งานได้และว่าง')
                ->color('success')
                ->icon(Heroicon::OutlinedCheckCircle),
            Stat::make('ถูกยืมอยู่', number_format((int) $assets->on_loan))
                ->description($overdue > 0 ? "เกินกำหนดคืน {$overdue} รายการ" : 'ไม่มีรายการเกินกำหนด')
                ->descriptionColor($overdue > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedHandRaised),
            Stat::make('ซ่อม / ชำรุด', number_format((int) $assets->in_repair).' / '.number_format((int) $assets->waiting_repair))
                ->description('กำลังซ่อม / ชำรุดรอส่งซ่อม')
                ->color('warning')
                ->icon(Heroicon::OutlinedWrenchScrewdriver),
            Stat::make('คำขอรออนุมัติ', number_format($pending))
                ->description('คำขอยืมที่ยังไม่ได้พิจารณา')
                ->url(LoanResource::getUrl())
                ->icon(Heroicon::OutlinedClipboardDocumentList),
        ];
    }
}
