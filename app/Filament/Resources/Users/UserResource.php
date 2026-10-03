<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Users are never deleted: ledger rows and loans point at them forever. Deactivate instead.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'ตั้งค่า';

    protected static ?string $modelLabel = 'ผู้ใช้';

    protected static ?string $pluralModelLabel = 'ผู้ใช้งาน';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        // An admin cannot lock themselves out by changing their own role or deactivating themselves.
        $isSelf = fn (?User $record): bool => $record?->is(auth()->user()) ?? false;

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('ชื่อ-นามสกุล')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('อีเมล')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('department_id')
                    ->label('หน่วยงาน')
                    ->relationship('department', 'name')
                    ->required(),
                Select::make('role')
                    ->label('บทบาท')
                    ->options(Role::class)
                    ->default(Role::Staff)
                    ->required()
                    ->disabled($isSelf),
                TextInput::make('password')
                    ->label('รหัสผ่าน')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน' : null)
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->label('ยืนยันรหัสผ่าน')
                    ->password()
                    ->revealable()
                    ->requiredWith('password')
                    ->dehydrated(false),
                Toggle::make('is_active')
                    ->label('ใช้งานอยู่')
                    ->helperText('ปิดแทนการลบ ผู้ใช้ที่ปิดแล้วจะเข้าระบบไม่ได้ แต่ประวัติยังอยู่ครบ')
                    ->default(true)
                    ->disabled($isSelf),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->with('department'))
            ->columns([
                TextColumn::make('name')
                    ->label('ชื่อ-นามสกุล')
                    ->weight(FontWeight::Medium)
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('department.name')
                    ->visibleFrom('md')
                    ->label('หน่วยงาน')
                    ->placeholder('-'),
                TextColumn::make('role')
                    ->visibleFrom('sm')
                    ->label('บทบาท')
                    ->badge(),
                TextColumn::make('is_active')
                    ->label('สถานะ')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'ใช้งานอยู่' : 'ปิดใช้งาน')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('role')->label('บทบาท')->options(Role::class),
                SelectFilter::make('department')->label('หน่วยงาน')->relationship('department', 'name'),
                TernaryFilter::make('is_active')->label('ใช้งานอยู่'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
