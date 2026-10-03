<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'ตั้งค่า';

    protected static ?string $modelLabel = 'หมวดหมู่';

    protected static ?string $pluralModelLabel = 'หมวดหมู่';

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
                TextInput::make('name')->label('ชื่อหมวดหมู่')->required(),
                TextInput::make('useful_life_years')->label('อายุการใช้งาน (ปี)')->numeric()->minValue(1)->maxValue(100),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('รหัส')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('name')->label('ชื่อหมวดหมู่')->weight(FontWeight::Medium)->searchable(),
                TextColumn::make('useful_life_years')->label('อายุการใช้งาน (ปี)')->placeholder('-'),
                TextColumn::make('assets_count')->label('ทรัพย์สิน')->counts('assets')->numeric(),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
