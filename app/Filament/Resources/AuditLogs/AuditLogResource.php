<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\ThaiDate;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Resources\Resource;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Read-only, admin-only view of who changed which row. No actions, matching the append-only table.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'ตั้งค่า';

    protected static ?string $modelLabel = 'บันทึกการแก้ไข';

    protected static ?string $pluralModelLabel = 'บันทึกการแก้ไข';

    protected static ?int $navigationSort = 99;

    private const TYPES = [
        Asset::class => 'ทรัพย์สิน',
        User::class => 'ผู้ใช้',
        Category::class => 'หมวดหมู่',
        Department::class => 'หน่วยงาน',
    ];

    private const EVENTS = [
        'created' => 'สร้าง',
        'updated' => 'แก้ไข',
        'deleted' => 'ลบ',
        'restored' => 'กู้คืน',
    ];

    private const FIELDS = [
        'asset_tag' => 'เลขครุภัณฑ์',
        'name' => 'ชื่อ',
        'code' => 'รหัส',
        'category_id' => 'หมวดหมู่',
        'department_id' => 'หน่วยงาน',
        'parent_id' => 'สังกัด',
        'custodian_id' => 'ผู้รับผิดชอบ',
        'serial_no' => 'Serial No.',
        'acquired_on' => 'วันที่ได้มา',
        'cost' => 'ราคา',
        'location_note' => 'ที่ตั้ง',
        'useful_life_years' => 'อายุการใช้งาน (ปี)',
        'email' => 'อีเมล',
        'password' => 'รหัสผ่าน',
        'role' => 'บทบาท',
        'is_active' => 'เปิดใช้งาน',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck)
            ->emptyStateHeading('ยังไม่มีบันทึกการแก้ไข')
            ->emptyStateDescription('ทุกครั้งที่สร้าง แก้ไข หรือลบ ทรัพย์สิน ผู้ใช้ หมวดหมู่ หรือหน่วยงาน จะมีบันทึกที่นี่')
            // Only assets are soft-deleted; their audit rows must still name them.
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'actor',
                'auditable' => fn (MorphTo $morphTo) => $morphTo->constrain([
                    Asset::class => fn (Builder $assets) => $assets->withTrashed(),
                ]),
            ]))
            ->columns([
                // Who and what are the point of this page, so they stay visible on a phone.
                TextColumn::make('created_at')
                    ->label('เวลา / ผู้ทำรายการ')
                    ->formatStateUsing(ThaiDate::formatter(withTime: true))
                    ->description(fn (AuditLog $record): string => $record->actor->name ?? 'ระบบ'),
                TextColumn::make('event')
                    ->visibleFrom('sm')
                    ->label('รายการ')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::EVENTS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject')
                    ->label('ข้อมูล')
                    ->wrap()
                    ->state(fn (AuditLog $record): string => self::describeSubject($record))
                    ->url(fn (AuditLog $record): ?string => $record->auditable instanceof Asset
                        ? AssetResource::getUrl('view', ['record' => $record->auditable])
                        : null),
                TextColumn::make('diff')
                    ->label('การเปลี่ยนแปลง')
                    ->state(fn (AuditLog $record): array => self::describeDiff($record))
                    ->listWithLineBreaks()
                    ->wrap()
                    ->placeholder('-'),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('auditable_type')->label('ประเภทข้อมูล')->options(self::TYPES),
                SelectFilter::make('event')->label('รายการ')->options(self::EVENTS),
                SelectFilter::make('actor')->label('ผู้ทำรายการ')->relationship('actor', 'name'),
            ])
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditLogs::route('/'),
        ];
    }

    private static function describeSubject(AuditLog $log): string
    {
        $subject = $log->auditable;
        $name = match (true) {
            $subject instanceof Asset => "{$subject->asset_tag} {$subject->name}",
            $subject !== null => $subject->name,
            default => "#{$log->auditable_id}",
        };

        return (self::TYPES[$log->auditable_type] ?? class_basename($log->auditable_type)).' · '.$name;
    }

    /**
     * e.g. ["ราคา: 12000.00 → 15000.00", "บทบาท: พนักงาน → ผู้ดูแลระบบ"]
     *
     * @return array<string>
     */
    private static function describeDiff(AuditLog $log): array
    {
        $blank = new $log->auditable_type;

        return collect($log->diff ?? [])
            ->map(fn (array $change, string $field): string => (self::FIELDS[$field] ?? $field).': '
                .self::describeValue($blank, $field, $change['from']).' → '.self::describeValue($blank, $field, $change['to']))
            ->values()
            ->all();
    }

    /**
     * Turns a stored raw value back into what the user saw: a name for an id, a label for an enum, a พ.ศ. date.
     */
    private static function describeValue(Model $blank, string $field, mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '—';
        }

        $relation = Str::camel(Str::beforeLast($field, '_id'));
        if (str_ends_with($field, '_id') && method_exists($blank, $relation) && $blank->{$relation}() instanceof BelongsTo) {
            return $blank->{$relation}()->getRelated()->newQuery()->find($raw)?->name ?? "#{$raw}";
        }

        $value = $blank->newInstance()->setRawAttributes([$field => $raw])->getAttribute($field);

        return match (true) {
            $value instanceof HasLabel => $value->getLabel(),
            $value instanceof CarbonInterface => ThaiDate::format($value),
            is_bool($value) => $value ? 'ใช่' : 'ไม่ใช่',
            default => (string) $value,
        };
    }
}
