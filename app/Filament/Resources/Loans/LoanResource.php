<?php

namespace App\Filament\Resources\Loans;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Enums\LoanStatus;
use App\Filament\Resources\Assets\AssetActions;
use App\Filament\Resources\Loans\Pages\ManageLoans;
use App\Filament\ThaiDate;
use App\Models\Asset;
use App\Models\Loan;
use App\Services\AssetLedger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'คำขอยืม';

    protected static ?string $pluralModelLabel = 'การยืม-คืน';

    protected static ?int $navigationSort = 2;

    /**
     * Create-only form; after that a loan moves only through the status actions.
     */
    public static function form(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema
            ->components([
                Select::make('asset_id')
                    ->label('ทรัพย์สิน')
                    ->relationship('asset', 'name', fn (Builder $query) => $query
                        ->where('condition', Condition::Usable)
                        ->where('availability', Availability::Available)
                        ->when(! $user->isOfficer(), fn (Builder $query) => $query->where('department_id', $user->department_id)))
                    ->getOptionLabelFromRecordUsing(fn (Asset $record): string => "{$record->asset_tag} · {$record->name}")
                    ->searchable(['asset_tag', 'name'])
                    ->helperText('แสดงเฉพาะทรัพย์สินที่ใช้งานได้และว่างอยู่')
                    ->required(),
                Select::make('borrower_id')
                    ->label('ผู้ยืม')
                    ->relationship('borrower', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->default($user->id)
                    ->disabled(! $user->isOfficer())
                    ->dehydrated()
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('due_on')
                    ->label('กำหนดคืน')
                    ->minDate(today())
                    ->default(today()->addDays(7))
                    ->live()
                    ->hint(ThaiDate::hint())
                    ->required(),
                Textarea::make('purpose')
                    ->label('วัตถุประสงค์')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateHeading('ยังไม่มีคำขอยืมในหมวดนี้')
            ->emptyStateDescription('กด "ขอยืมทรัพย์สิน" ด้านบนเพื่อสร้างคำขอใหม่ หรือเลือกแท็บอื่น')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'borrower', 'approver']))
            ->columns([
                TextColumn::make('asset.name')
                    ->label('ทรัพย์สิน')
                    ->weight(FontWeight::Medium)
                    ->wrap()
                    ->description(fn (Loan $record): string => $record->asset->asset_tag)
                    ->searchable(['name', 'asset_tag']),
                TextColumn::make('borrower.name')
                    ->visibleFrom('md')
                    ->label('ผู้ยืม')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('สถานะ')
                    ->badge(),
                TextColumn::make('requested_at')
                    ->visibleFrom('lg')
                    ->label('วันที่ขอ')
                    ->formatStateUsing(ThaiDate::formatter())
                    ->sortable(),
                TextColumn::make('due_on')
                    ->visibleFrom('sm')
                    ->label('กำหนดคืน')
                    ->formatStateUsing(ThaiDate::formatter())
                    ->sortable()
                    ->color(fn (Loan $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (Loan $record): ?string => $record->isOverdue() ? 'เกินกำหนด' : null),
                TextColumn::make('approver.name')
                    ->visibleFrom('lg')
                    ->label('ผู้อนุมัติ')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('purpose')
                    ->label('วัตถุประสงค์')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::approveAction(),
                    self::rejectAction(),
                    self::handOverAction(),
                    self::returnAction(),
                    self::cancelAction(),
                ]),
            ]);
    }

    /**
     * Staff see only loans they requested or borrowed.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(! $user->isOfficer(), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('requester_id', $user->id)
                ->orWhere('borrower_id', $user->id)));
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->isOfficer()) {
            return null;
        }

        $pending = Loan::query()->where('status', LoanStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'คำขอรออนุมัติ';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLoans::route('/'),
        ];
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('อนุมัติ')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Loan $record): bool => auth()->user()->isOfficer() && $record->status === LoanStatus::Pending)
            ->action(fn (Loan $record, Action $action) => AssetActions::run($action, fn () => AssetLedger::approve($record, auth()->user())))
            ->successNotificationTitle('อนุมัติแล้ว');
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('ไม่อนุมัติ')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Loan $record): bool => auth()->user()->isOfficer() && $record->status === LoanStatus::Pending)
            ->action(fn (Loan $record, Action $action) => AssetActions::run($action, fn () => AssetLedger::reject($record, auth()->user())))
            ->successNotificationTitle('ปฏิเสธคำขอแล้ว');
    }

    private static function handOverAction(): Action
    {
        return Action::make('handOver')
            ->label('ส่งมอบ')
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('สถานะทรัพย์สินจะเปลี่ยนเป็น "ถูกยืม" ตอนส่งมอบ ไม่ใช่ตอนอนุมัติ')
            ->visible(fn (Loan $record): bool => auth()->user()->isOfficer() && $record->status === LoanStatus::Approved)
            ->action(fn (Loan $record, Action $action) => AssetActions::run($action, fn () => AssetLedger::handOver($record, auth()->user())))
            ->successNotificationTitle('ส่งมอบแล้ว');
    }

    private static function returnAction(): Action
    {
        return Action::make('receiveReturn')
            ->label('รับคืน')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->visible(fn (Loan $record): bool => auth()->user()->isOfficer() && $record->status === LoanStatus::HandedOver)
            ->schema([
                AssetActions::conditionSelect('สภาพตอนคืน'),
                Textarea::make('note')->label('บันทึก'),
            ])
            ->action(fn (Loan $record, array $data, Action $action) => AssetActions::run($action, fn () => AssetLedger::receiveReturn(
                $record, auth()->user(), Condition::from($data['condition']), $data['note'],
            )))
            ->successNotificationTitle('รับคืนแล้ว');
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('ยกเลิกคำขอ')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Loan $record): bool => in_array($record->status, [LoanStatus::Pending, LoanStatus::Approved], true)
                && (auth()->user()->isOfficer() || $record->requester_id === auth()->id()))
            ->action(fn (Loan $record, Action $action) => AssetActions::run($action, fn () => AssetLedger::cancel($record)))
            ->successNotificationTitle('ยกเลิกแล้ว');
    }
}
