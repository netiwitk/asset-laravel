<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Filament\ThaiDate;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('ข้อมูลทรัพย์สิน')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('asset_tag')->label('เลขครุภัณฑ์')->fontFamily('mono')->copyable(),
                        TextEntry::make('name')->label('ชื่อทรัพย์สิน'),
                        TextEntry::make('category.name')->label('หมวดหมู่'),
                        TextEntry::make('department.name')->label('หน่วยงาน'),
                        TextEntry::make('custodian.name')->label('ผู้รับผิดชอบ')->placeholder('-'),
                        TextEntry::make('serial_no')->label('Serial No.')->placeholder('-'),
                        TextEntry::make('acquired_on')->label('วันที่ได้มา')->formatStateUsing(ThaiDate::formatter())->placeholder('-'),
                        TextEntry::make('cost')->label('ราคา')->money('THB')->placeholder('-'),
                        TextEntry::make('location_note')->label('ที่ตั้ง')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('สถานะ')
                    ->description('เปลี่ยนได้ผ่านปุ่มด้านบนเท่านั้น และทุกครั้งจะถูกบันทึกในประวัติ')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('condition')->label('สภาพ')->badge(),
                        TextEntry::make('availability')->label('การใช้งาน')->badge(),
                        TextEntry::make('deleted_at')->label('ลบเมื่อ')->formatStateUsing(ThaiDate::formatter(withTime: true))->visible(fn ($record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
