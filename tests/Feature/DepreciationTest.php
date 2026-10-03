<?php

namespace Tests\Feature;

use App\Enums\Condition;
use App\Enums\Role;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use App\Services\AssetLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DepreciationTest extends TestCase
{
    use RefreshDatabase;

    private function asset(string $cost, ?int $usefulLifeYears = 5): Asset
    {
        return (new Asset(['cost' => $cost, 'acquired_on' => '2021-01-01']))
            ->setRelation('category', new Category(['useful_life_years' => $usefulLifeYears]));
    }

    public function test_value_falls_in_a_straight_line_and_stops_at_one_baht(): void
    {
        // 2021-01-01 to 2026-01-01 is 1,826 days (2024 is a leap year); day 913 is the middle.
        $this->assertSame('36500.00', $this->asset('36500')->bookValue(Carbon::parse('2020-12-01')));
        $this->assertSame('18250.00', $this->asset('36500')->bookValue(Carbon::parse('2021-01-01')->addDays(913)));
        $this->assertSame('1.00', $this->asset('36500')->bookValue(Carbon::parse('2030-01-01')));
        $this->assertSame('0.00', $this->asset('0')->bookValue(Carbon::parse('2030-01-01')));
    }

    public function test_no_value_without_a_useful_life_and_zero_once_disposed(): void
    {
        $this->assertNull($this->asset('36500', usefulLifeYears: null)->bookValue(Carbon::parse('2023-01-01')));
        $this->assertSame('0.00', $this->asset('36500')->forceFill(['condition' => Condition::Disposed])->bookValue(Carbon::parse('2023-01-01')));
    }

    public function test_asset_page_shows_the_book_value(): void
    {
        $officer = User::factory()->create(['role' => Role::Officer]);
        $category = Category::factory()->create(['useful_life_years' => 5]);
        $asset = AssetLedger::register(
            ['asset_tag' => 'COM-68-0001', 'name' => 'Notebook', 'category_id' => $category->id, 'cost' => 36500, 'acquired_on' => '2021-01-01'],
            Department::factory()->create()->id,
            $officer,
        );

        $this->actingAs($officer);
        Livewire::test(ViewAsset::class, ['record' => $asset->id])
            ->assertSee('มูลค่าคงเหลือ')
            ->assertSee('ค่าเสื่อมราคาแบบเส้นตรง อายุ 5 ปี');
    }
}
