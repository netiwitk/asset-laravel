<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\ThaiDate;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('ส่งออก CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
            CreateAction::make(),
        ];
    }

    /**
     * The register exactly as the user sees it: same tab, filters, search, sort and department scope.
     * The BOM makes Excel read the file as UTF-8, so Thai text is not garbled.
     *
     * ponytail: loads every row and sends the file through Livewire; a register of 1M rows needs a queued export to a file.
     */
    public function exportCsv(): StreamedResponse
    {
        $assets = $this->getTableQueryForExport()->with(['category', 'department', 'custodian'])->get();

        return response()->streamDownload(function () use ($assets): void {
            $csv = fopen('php://output', 'w');
            fwrite($csv, "\u{FEFF}");
            fputcsv($csv, ['เลขครุภัณฑ์', 'ชื่อทรัพย์สิน', 'หมวดหมู่', 'หน่วยงาน', 'ผู้รับผิดชอบ', 'สภาพ', 'การใช้งาน', 'Serial No.', 'วันที่ได้มา', 'ราคา', 'มูลค่าคงเหลือ', 'ที่ตั้ง'], escape: '');

            foreach ($assets as $asset) {
                fputcsv($csv, array_map(self::spreadsheetSafe(...), [
                    $asset->asset_tag, $asset->name, $asset->category->name, $asset->department->name,
                    $asset->custodian?->name, $asset->condition->getLabel(), $asset->availability->getLabel(),
                    $asset->serial_no, ThaiDate::format($asset->acquired_on), $asset->cost, $asset->bookValue(),
                    $asset->location_note,
                ]), escape: '');
            }

            fclose($csv);
        }, 'assets-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Text starting with = + - @ would run as a formula when the file is opened in Excel (CSV injection).
     * Prices and book values are never negative, so numbers pass through untouched.
     */
    private static function spreadsheetSafe(?string $text): ?string
    {
        return $text !== null && preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }

    /**
     * Quick filters above the table, each with its count in the user's scope.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tab = fn (string $label, ?Closure $scope = null): Tab => Tab::make($label)
            ->modifyQueryUsing($scope ?? fn (Builder $query) => $query)
            ->badge(AssetResource::getEloquentQuery()->when($scope, $scope)->count());

        return [
            'all' => $tab('ทั้งหมด'),
            'available' => $tab('ว่าง', fn (Builder $query) => $query->where('availability', Availability::Available)->where('condition', '<>', Condition::Disposed)),
            'on_loan' => $tab('ถูกยืม', fn (Builder $query) => $query->where('availability', Availability::OnLoan)),
            'in_repair' => $tab('ส่งซ่อม', fn (Builder $query) => $query->where('availability', Availability::InRepair)),
            'damaged' => $tab('ชำรุด', fn (Builder $query) => $query->where('condition', Condition::Damaged)),
        ];
    }
}
