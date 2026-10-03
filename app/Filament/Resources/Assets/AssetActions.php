<?php

namespace App\Filament\Resources\Assets;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Filament\ThaiDate;
use App\Models\Asset;
use App\Models\Department;
use App\Services\AssetLedger;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Status-changing buttons for an asset. Each one calls AssetLedger,
 * so the edit form never needs condition, availability or department fields.
 */
class AssetActions
{
    /**
     * @return array<Action>
     */
    public static function all(): array
    {
        return [self::sendToRepair(), self::receiveFromRepair(), self::transfer(), self::dispose()];
    }

    public static function sendToRepair(): Action
    {
        return Action::make('sendToRepair')
            ->label('ส่งซ่อม')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('warning')
            ->visible(fn (Asset $record): bool => self::canChange($record) && $record->availability === Availability::Available)
            ->schema([
                TextInput::make('vendor')->label('ร้าน / ผู้รับซ่อม'),
                DatePicker::make('expected_return_on')->label('คาดว่าจะได้คืน')->minDate(today())->live()->hint(ThaiDate::hint()),
                Textarea::make('note')->label('อาการเสีย'),
            ])
            ->action(fn (Asset $record, array $data, Action $action) => self::run($action, fn () => AssetLedger::sendToRepair(
                $record, auth()->user(), $data['vendor'], $data['expected_return_on'], $data['note'],
            )))
            ->successNotificationTitle('ส่งซ่อมแล้ว');
    }

    /**
     * @param  (Closure(Model): Asset)|null  $assetOf  how to reach the asset from the row, e.g. a repair order
     */
    public static function receiveFromRepair(?Closure $assetOf = null): Action
    {
        $assetOf ??= fn (Asset $record): Asset => $record;

        return Action::make('receiveFromRepair')
            ->label('รับคืนจากซ่อม')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->visible(fn (Model $record): bool => self::canChange($assetOf($record)) && $assetOf($record)->availability === Availability::InRepair)
            ->schema([
                self::conditionSelect('ผลการซ่อม'),
                TextInput::make('cost')->label('ค่าซ่อม')->numeric()->minValue(0)->prefix('฿'),
                Textarea::make('note')->label('บันทึก'),
            ])
            ->action(fn (Model $record, array $data, Action $action) => self::run($action, fn () => AssetLedger::receiveFromRepair(
                $assetOf($record), auth()->user(), Condition::from($data['condition']), $data['cost'], $data['note'],
            )))
            ->successNotificationTitle('รับคืนจากซ่อมแล้ว');
    }

    public static function transfer(): Action
    {
        return Action::make('transfer')
            ->label('โอนย้ายหน่วยงาน')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('info')
            ->visible(fn (Asset $record): bool => self::canChange($record) && $record->availability === Availability::Available)
            ->schema(fn (Asset $record): array => [
                Select::make('department_id')
                    ->label('ไปยังหน่วยงาน')
                    ->options(Department::query()->where('is_active', true)->whereKeyNot($record->department_id)->pluck('name', 'id'))
                    ->required(),
                Textarea::make('note')->label('เหตุผล'),
            ])
            ->action(fn (Asset $record, array $data, Action $action) => self::run($action, fn () => AssetLedger::transfer(
                $record, auth()->user(), (int) $data['department_id'], $data['note'],
            )))
            ->successNotificationTitle('โอนย้ายแล้ว');
    }

    public static function dispose(): Action
    {
        return Action::make('dispose')
            ->label('จำหน่าย')
            ->icon(Heroicon::OutlinedArchiveBoxXMark)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('การจำหน่ายย้อนกลับไม่ได้ ประวัติจะยังอยู่ในระบบ')
            ->visible(fn (Asset $record): bool => self::canChange($record) && $record->availability === Availability::Available)
            ->schema([
                Textarea::make('note')->label('เหตุผลการจำหน่าย')->required(),
            ])
            ->action(fn (Asset $record, array $data, Action $action) => self::run($action, fn () => AssetLedger::dispose(
                $record, auth()->user(), $data['note'],
            )))
            ->successNotificationTitle('จำหน่ายแล้ว');
    }

    /**
     * Runs a ledger call and turns a broken business rule into a failure notification.
     */
    public static function run(Action $action, Closure $callback): void
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            $action->failureNotificationTitle($exception->getMessage());
            $action->failure();
        }
    }

    public static function conditionSelect(string $label): Select
    {
        return Select::make('condition')
            ->label($label)
            ->options([
                Condition::Usable->value => Condition::Usable->getLabel(),
                Condition::Damaged->value => Condition::Damaged->getLabel(),
            ])
            ->default(Condition::Usable->value)
            ->required();
    }

    private static function canChange(Asset $record): bool
    {
        return auth()->user()->isOfficer() && ! $record->trashed() && $record->condition !== Condition::Disposed;
    }
}
