<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Models\Department;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Departments are deactivated, never deleted: assets and ledger rows point at them.
 */
class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'ตั้งค่า';

    protected static ?string $modelLabel = 'หน่วยงาน';

    protected static ?string $pluralModelLabel = 'หน่วยงาน';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label('รหัส')->required()->maxLength(32)->unique(ignoreRecord: true),
                TextInput::make('name')->label('ชื่อหน่วยงาน')->required(),
                Select::make('parent_id')
                    ->label('สังกัด')
                    ->relationship('parent', 'name', fn ($query, ?Department $record) => $query->when($record, fn ($query) => $query->whereKeyNot($record->id))),
                Toggle::make('is_active')->label('ใช้งานอยู่')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('รหัส')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('name')->label('ชื่อหน่วยงาน')->weight(FontWeight::Medium)->searchable(),
                TextColumn::make('parent.name')->label('สังกัด')->placeholder('-'),
                TextColumn::make('assets_count')->label('ทรัพย์สิน')->counts('assets')->numeric(),
                TextColumn::make('is_active')->label('สถานะ')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'ใช้งานอยู่' : 'ปิดใช้งาน')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDepartments::route('/'),
        ];
    }
}
