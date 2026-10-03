<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Filament\Resources\Assets\AssetActions;
use App\Filament\ThaiDate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('asset_tag')
            ->emptyStateIcon(Heroicon::OutlinedCube)
            ->emptyStateHeading('ไม่พบทรัพย์สิน')
            ->emptyStateDescription('ลองเปลี่ยนคำค้นหา ล้างตัวกรอง หรือเลือกแท็บ "ทั้งหมด"')
            ->columns([
                TextColumn::make('asset_tag')
                    ->visibleFrom('sm')
                    ->label('เลขครุภัณฑ์')
                    ->fontFamily('mono')
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('คัดลอกเลขครุภัณฑ์แล้ว')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('ชื่อทรัพย์สิน')
                    ->weight(FontWeight::Medium)
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->location_note),
                TextColumn::make('category.name')
                    ->visibleFrom('lg')
                    ->label('หมวดหมู่')
                    ->toggleable(),
                TextColumn::make('department.name')
                    ->visibleFrom('md')
                    ->label('หน่วยงาน')
                    ->toggleable(),
                TextColumn::make('condition')
                    ->visibleFrom('md')
                    ->label('สภาพ')
                    ->badge(),
                TextColumn::make('availability')
                    ->label('การใช้งาน')
                    ->badge(),
                TextColumn::make('custodian.name')
                    ->label('ผู้รับผิดชอบ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cost')
                    ->label('ราคา')
                    ->money('THB')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('acquired_on')
                    ->label('วันที่ได้มา')
                    ->formatStateUsing(ThaiDate::formatter())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department')->label('หน่วยงาน')->relationship('department', 'name'),
                SelectFilter::make('category')->label('หมวดหมู่')->relationship('category', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                ActionGroup::make(AssetActions::all()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Checked per row against AssetPolicy: a selection may mix idle assets with ones on loan.
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                    RestoreBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
