<?php

namespace Tests\Unit;

use App\Filament\ThaiDate;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ThaiDateTest extends TestCase
{
    public function test_formats_in_buddhist_era_and_bangkok_time(): void
    {
        $this->assertSame('6 ม.ค. 2565', ThaiDate::format('2022-01-06'));
        // 23:30 UTC is already the next morning in Bangkok.
        $this->assertSame('1 ต.ค. 2569 06:30', ThaiDate::format(Carbon::parse('2026-09-30 23:30:00', 'UTC'), withTime: true));
        $this->assertNull(ThaiDate::format(null));
    }
}
