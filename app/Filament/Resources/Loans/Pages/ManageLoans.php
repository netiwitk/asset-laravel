<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

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
}
