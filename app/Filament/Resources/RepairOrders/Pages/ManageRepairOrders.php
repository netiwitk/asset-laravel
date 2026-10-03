<?php

namespace App\Filament\Resources\RepairOrders\Pages;

use App\Filament\Resources\RepairOrders\RepairOrderResource;
use App\Models\RepairOrder;
use Closure;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageRepairOrders extends ManageRecords
{
    protected static string $resource = RepairOrderResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tab = fn (string $label, ?Closure $scope = null): Tab => Tab::make($label)
            ->modifyQueryUsing($scope ?? fn (Builder $query) => $query)
            ->badge(RepairOrder::query()->when($scope, $scope)->count());

        return [
            'all' => $tab('ทั้งหมด'),
            'open' => $tab('กำลังซ่อม', fn (Builder $query) => $query->whereNull('finished_on')),
            'overdue' => $tab('เลยกำหนดรับคืน', fn (Builder $query) => $query->overdue()),
            'finished' => $tab('ซ่อมเสร็จ', fn (Builder $query) => $query->whereNotNull('finished_on')),
        ];
    }
}
