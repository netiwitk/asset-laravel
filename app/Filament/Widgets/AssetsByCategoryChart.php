<?php

namespace App\Filament\Widgets;

use App\Enums\Condition;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Category;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class AssetsByCategoryChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'ทรัพย์สินตามหมวดหมู่';

    protected ?string $description = 'ไม่นับรายการที่จำหน่ายแล้ว';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $totals = AssetResource::getEloquentQuery()
            ->where('condition', '<>', Condition::Disposed)
            ->toBase()
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');
        $names = Category::query()->whereKey($totals->keys())->pluck('name', 'id');

        $colors = Filament::getCurrentOrDefaultPanel()->getColors();
        $palette = [$colors['primary'][500], $colors['accent'][400], $colors['primary'][300], $colors['accent'][600], $colors['primary'][700], $colors['gray'][400]];

        return [
            'datasets' => [[
                'data' => $totals->values()->all(),
                'backgroundColor' => array_slice(array_pad($palette, $totals->count(), $colors['gray'][300]), 0, $totals->count()),
                'borderWidth' => 0,
                'hoverOffset' => 6,
            ]],
            'labels' => $totals->keys()->map(fn (int $id): string => $names[$id] ?? '-')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '64%',
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
