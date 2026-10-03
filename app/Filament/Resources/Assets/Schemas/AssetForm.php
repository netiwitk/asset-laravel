<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Filament\ThaiDate;
use App\Models\Department;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

/**
 * No condition / availability fields here on purpose: those change only through
 * the ledger actions. Department is chosen once at registration, then moved by "transfer".
 */
class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ข้อมูลทรัพย์สิน')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('asset_tag')
                            ->label('เลขครุภัณฑ์')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->withoutTrashed()),
                        TextInput::make('name')
                            ->label('ชื่อทรัพย์สิน')
                            ->required()
                            ->maxLength(255),
                        Select::make('category_id')
                            ->label('หมวดหมู่')
                            ->relationship('category', 'name')
                            ->required(),
                        Select::make('department_id')
                            ->label('หน่วยงาน')
                            ->options(fn () => Department::query()->where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->visibleOn('create'),
                        Select::make('custodian_id')
                            ->label('ผู้รับผิดชอบ')
                            ->relationship('custodian', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('serial_no')
                            ->label('Serial No.'),
                        DatePicker::make('acquired_on')
                            ->label('วันที่ได้มา')
                            ->maxDate(today())
                            ->live()
                            ->hint(ThaiDate::hint()),
                        TextInput::make('cost')
                            ->label('ราคา')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('฿'),
                        TextInput::make('location_note')
                            ->label('ที่ตั้ง')
                            ->placeholder('เช่น อาคาร A ชั้น 3 ห้อง 301'),
                    ]),
            ]);
    }
}
