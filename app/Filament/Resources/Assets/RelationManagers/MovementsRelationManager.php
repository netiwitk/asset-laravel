<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Enums\MovementType;
use App\Filament\ThaiDate;
use App\Models\AssetMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of the ledger: no create/edit/delete actions, matching the append-only table.
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'ประวัติการเคลื่อนไหว';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['actor', 'fromDepartment', 'toDepartment']))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('เวลา')
                    ->formatStateUsing(ThaiDate::formatter(withTime: true)),
                TextColumn::make('type')
                    ->label('รายการ')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('change')
                    ->visibleFrom('md')
                    ->label('การเปลี่ยนแปลง')
                    ->state(fn (AssetMovement $record): string => self::describeChange($record)),
                TextColumn::make('actor.name')
                    ->visibleFrom('lg')
                    ->label('ผู้บันทึก'),
                TextColumn::make('note')
                    ->visibleFrom('md')
                    ->label('หมายเหตุ')
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->paginated([10, 25, 50]);
    }

    private static function describeChange(AssetMovement $movement): string
    {
        if ($movement->type === MovementType::OpeningBalance) {
            return implode(' · ', [$movement->to_condition?->getLabel(), $movement->to_availability?->getLabel(), $movement->toDepartment?->name]);
        }

        $changes = [];

        if ($movement->from_availability !== $movement->to_availability) {
            $changes[] = ($movement->from_availability?->getLabel() ?? '—').' → '.$movement->to_availability?->getLabel();
        }
        if ($movement->from_condition !== $movement->to_condition) {
            $changes[] = ($movement->from_condition?->getLabel() ?? '—').' → '.$movement->to_condition?->getLabel();
        }
        if ($movement->from_department_id !== $movement->to_department_id) {
            $changes[] = ($movement->fromDepartment?->name ?? '—').' → '.$movement->toDepartment?->name;
        }

        return $changes === [] ? '-' : implode(' · ', $changes);
    }
}
