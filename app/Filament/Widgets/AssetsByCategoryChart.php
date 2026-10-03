<?php

namespace App\Filament\Widgets;

use App\Enums\Condition;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Category;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class AssetsByCategoryChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'ทรัพย์สินตามหมวดหมู่';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    /** @var array<string, int>|null */
    private ?array $totals = null;

    /**
     * Category name => assets in it (not disposed), largest first, in the user's scope.
     *
     * @return array<string, int>
     */
    private function totals(): array
    {
        if ($this->totals !== null) {
            return $this->totals;
        }

        $counts = AssetResource::getEloquentQuery()
            ->where('condition', '<>', Condition::Disposed)
            ->toBase()
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');
        $names = Category::query()->whereKey($counts->keys())->pluck('name', 'id');

        return $this->totals = $counts
            ->mapWithKeys(fn ($total, $id): array => [$names[$id] ?? '-' => (int) $total])
            ->sortDesc()
            ->all();
    }

    /**
     * Shown under the heading and used as the chart's aria-label.
     */
    public function getDescription(): string
    {
        $totals = $this->totals();
        $largest = array_key_first($totals);

        return 'รวม '.array_sum($totals).' ชิ้น ไม่นับที่จำหน่ายแล้ว'
            .($largest ? ' · มากที่สุด: '.$largest.' '.$totals[$largest].' ชิ้น' : '');
    }

    protected function getData(): array
    {
        $totals = collect($this->totals());

        $colors = Filament::getCurrentOrDefaultPanel()->getColors();
        $palette = [$colors['primary'][500], $colors['accent'][400], $colors['primary'][300], $colors['accent'][600], $colors['primary'][700], $colors['gray'][400]];

        return [
            'datasets' => [[
                'data' => $totals->values()->all(),
                'backgroundColor' => array_slice(array_pad($palette, $totals->count(), $colors['gray'][300]), 0, $totals->count()),
                'borderWidth' => 0,
                'hoverOffset' => 6,
            ]],
            'labels' => $totals->keys()->all(),
        ];
    }

    /**
     * RawJs so the browser can honour prefers-reduced-motion.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : {},
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: { legend: { position: 'bottom' } },
                scales: { x: { display: false }, y: { display: false } },
            }
        JS);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
