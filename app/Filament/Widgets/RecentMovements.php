<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\ThaiDate;
use App\Models\AssetMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentMovements extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'ความเคลื่อนไหวล่าสุด';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AssetMovement::query()
                ->whereIn('asset_id', AssetResource::getEloquentQuery()->select('id'))
                ->with(['asset', 'actor'])
                ->latest('occurred_at')
                ->latest('id'))
            // TableWidget defaults to simple (next/previous only); show page numbers and the total.
            ->paginationMode(PaginationMode::Default)
            ->paginated([10])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('เวลา')
                    ->since()
                    ->tooltip(fn (AssetMovement $record): ?string => ThaiDate::format($record->occurred_at, withTime: true)),
                TextColumn::make('asset.name')
                    ->label('ทรัพย์สิน')
                    ->description(fn (AssetMovement $record): string => $record->asset->asset_tag)
                    ->url(fn (AssetMovement $record): string => AssetResource::getUrl('view', ['record' => $record->asset])),
                TextColumn::make('type')
                    ->label('รายการ')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('actor.name')
                    ->label('ผู้บันทึก'),
                TextColumn::make('note')
                    ->label('หมายเหตุ')
                    ->placeholder('-')
                    ->limit(50),
            ]);
    }
}
