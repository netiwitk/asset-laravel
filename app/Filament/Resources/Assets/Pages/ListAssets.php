<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Filament\Resources\Assets\AssetResource;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
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
