<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanResource;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageLoans extends ManageRecords
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('ขอยืมทรัพย์สิน')
                ->mutateDataUsing(fn (array $data): array => [
                    ...$data,
                    'requester_id' => auth()->id(),
                    'borrower_id' => auth()->user()->isOfficer() ? $data['borrower_id'] : auth()->id(),
                    'requested_at' => now(),
                ]),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tab = fn (string $label, ?Closure $scope = null): Tab => Tab::make($label)
            ->modifyQueryUsing($scope ?? fn (Builder $query) => $query)
            ->badge(LoanResource::getEloquentQuery()->when($scope, $scope)->count());

        return [
            'all' => $tab('ทั้งหมด'),
            'pending' => $tab('รออนุมัติ', fn (Builder $query) => $query->where('status', LoanStatus::Pending)),
            'approved' => $tab('รอส่งมอบ', fn (Builder $query) => $query->where('status', LoanStatus::Approved)),
            'handed_over' => $tab('ถูกยืมอยู่', fn (Builder $query) => $query->where('status', LoanStatus::HandedOver)),
            'overdue' => $tab('เกินกำหนด', fn (Builder $query) => $query->overdue()),
        ];
    }
}
