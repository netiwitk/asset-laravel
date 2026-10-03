<?php

namespace App\Filament\Widgets;

use App\Enums\MovementType;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\ThaiDate;
use App\Models\AssetMovement;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class LoansPerWeekChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'การยืม–คืนรายสัปดาห์';

    protected ?string $description = '8 สัปดาห์ล่าสุด';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    /**
     * Ledger rows of one type per week, oldest first, keyed by the week's Monday ("29 ก.ย.").
     * Scoped like the asset list, so staff only count their own department.
     *
     * @return array<string, int>
     */
    public static function weeklyCounts(MovementType $type, int $weeks = 8): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);
        $dates = AssetMovement::query()
            ->where('type', $type)
            ->where('occurred_at', '>=', $start)
            ->whereIn('asset_id', AssetResource::getEloquentQuery()->select('id'))
            ->pluck('occurred_at');

        $counts = [];
        foreach (range(0, $weeks - 1) as $offset) {
            $week = $start->copy()->addWeeks($offset);
            $counts[ThaiDate::dayMonth($week)] = $dates->filter(fn ($date): bool => $date->between($week, $week->copy()->endOfWeek()))->count();
        }

        return $counts;
    }

    protected function getData(): array
    {
        $borrows = self::weeklyCounts(MovementType::Borrow);
        $colors = Filament::getCurrentOrDefaultPanel()->getColors();

        return [
            'datasets' => [
                [
                    'label' => 'ยืม',
                    'data' => array_values($borrows),
                    // A shade darker than the accent bars so the two series stay easy to tell apart.
                    'backgroundColor' => $colors['primary'][600],
                    'borderRadius' => 6,
                    'maxBarThickness' => 22,
                ],
                [
                    'label' => 'คืน',
                    'data' => array_values(self::weeklyCounts(MovementType::Return)),
                    'backgroundColor' => $colors['accent'][400],
                    'borderRadius' => 6,
                    'maxBarThickness' => 22,
                ],
            ],
            'labels' => array_keys($borrows),
        ];
    }

    protected function getOptions(): array
    {
        return [
            // Fill the fixed-height frame so both chart cards come out the same size.
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
