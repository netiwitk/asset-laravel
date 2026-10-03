<?php

namespace App\Filament\Resources\RepairOrders;

use App\Filament\Resources\Assets\AssetActions;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\RepairOrders\Pages\ManageRepairOrders;
use App\Filament\ThaiDate;
use App\Models\Asset;
use App\Models\RepairOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repair orders are opened by the "send to repair" action on an asset; this page lists and closes them.
 */
class RepairOrderResource extends Resource
{
    protected static ?string $model = RepairOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'งานซ่อม';

    protected static ?string $pluralModelLabel = 'งานซ่อม';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()->isOfficer();
    }

    public static function getNavigationBadge(): ?string
    {
        $open = RepairOrder::query()->whereNull('finished_on')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'กำลังซ่อม';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sent_on', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'requester']))
            ->columns([
                TextColumn::make('asset.name')
                    ->label('ทรัพย์สิน')
                    ->description(fn (RepairOrder $record): string => $record->asset->asset_tag)
                    ->url(fn (RepairOrder $record): string => AssetResource::getUrl('view', ['record' => $record->asset]))
                    ->searchable(['name', 'asset_tag']),
                TextColumn::make('vendor')
                    ->label('ร้าน / ผู้รับซ่อม')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('สถานะ')
                    ->state(fn (RepairOrder $record): string => $record->finished_on ? 'ซ่อมเสร็จ' : 'กำลังซ่อม')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'ซ่อมเสร็จ' ? 'success' : 'warning'),
                TextColumn::make('sent_on')
                    ->label('วันที่ส่ง')
                    ->formatStateUsing(ThaiDate::formatter())
                    ->sortable(),
                TextColumn::make('expected_return_on')
                    ->label('คาดว่าจะได้คืน')
                    ->formatStateUsing(ThaiDate::formatter())
                    ->placeholder('-')
                    ->color(fn (RepairOrder $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (RepairOrder $record): ?string => $record->isOverdue() ? 'เลยกำหนด' : null),
                TextColumn::make('finished_on')
                    ->label('วันที่รับคืน')
                    ->formatStateUsing(ThaiDate::formatter())
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('cost')
                    ->label('ค่าซ่อม')
                    ->money('THB')
                    ->placeholder('-'),
                TextColumn::make('requester.name')
                    ->label('ผู้ส่งซ่อม')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('result_note')
                    ->label('ผลการซ่อม')
                    ->placeholder('-')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('finished')
                    ->label('สถานะ')
                    ->placeholder('ทั้งหมด')
                    ->trueLabel('ซ่อมเสร็จ')
                    ->falseLabel('กำลังซ่อม')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('finished_on'),
                        false: fn (Builder $query) => $query->whereNull('finished_on'),
                    ),
                Filter::make('overdue')
                    ->label('เลยกำหนดรับคืน')
                    ->query(fn (Builder $query) => $query->overdue()),
            ])
            ->recordActions([
                AssetActions::receiveFromRepair(fn (RepairOrder $record): Asset => $record->asset),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRepairOrders::route('/'),
        ];
    }
}
