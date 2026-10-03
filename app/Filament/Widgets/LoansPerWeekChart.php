<?php

namespace App\Filament\Widgets;

use App\Enums\MovementType;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\ThaiDate;
use App\Models\AssetMovement;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class LoansPerWeekChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'การยืม–คืนรายสัปดาห์';

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

    /** @var array{borrow: array<string, int>, return: array<string, int>}|null */
    private ?array $series = null;

    /**
     * @return array{borrow: array<string, int>, return: array<string, int>}
     */
    private function series(): array
    {
        return $this->series ??= [
            'borrow' => self::weeklyCounts(MovementType::Borrow),
            'return' => self::weeklyCounts(MovementType::Return),
        ];
    }

    /**
     * Shown under the heading and used as the chart's aria-label, so screen readers get the totals.
     */
    public function getDescription(): string
    {
        return '8 สัปดาห์ล่าสุด · ยืม '.array_sum($this->series()['borrow']).' ครั้ง · คืน '.array_sum($this->series()['return']).' ครั้ง';
    }

    protected function getData(): array
    {
        $borrows = $this->series()['borrow'];
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
                    'data' => array_values($this->series()['return']),
                    'backgroundColor' => $colors['accent'][400],
                    'borderRadius' => 6,
                    'maxBarThickness' => 22,
                ],
            ],
            'labels' => array_keys($borrows),
        ];
    }

    /**
     * RawJs so the browser can honour prefers-reduced-motion; maintainAspectRatio off fills the fixed frame.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : {},
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
