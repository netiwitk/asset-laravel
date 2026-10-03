<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Filament\Resources\Assets\AssetActions;
use App\Filament\ThaiDate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
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
            ->columns([
                TextColumn::make('asset_tag')
                    ->label('เลขครุภัณฑ์')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('ชื่อทรัพย์สิน')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->location_note),
                TextColumn::make('category.name')
                    ->label('หมวดหมู่')
                    ->toggleable(),
                TextColumn::make('department.name')
                    ->label('หน่วยงาน')
                    ->toggleable(),
                TextColumn::make('condition')
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
                SelectFilter::make('availability')->label('การใช้งาน')->options(Availability::class),
                SelectFilter::make('condition')->label('สภาพ')->options(Condition::class),
                SelectFilter::make('department')->label('หน่วยงาน')->relationship('department', 'name'),
                SelectFilter::make('category')->label('หมวดหมู่')->relationship('category', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
