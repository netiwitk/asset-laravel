<?php

namespace App\Filament;

use Closure;
use Illuminate\Support\Carbon;
use IntlDateFormatter;

/**
 * Thai display dates in the Buddhist era (พ.ศ.), e.g. "6 ม.ค. 2565".
 * PHP intl renders the Buddhist calendar; the Bangkok zone is explicit so a UTC value still shows Thai time.
 */
class ThaiDate
{
    public static function format(mixed $value, bool $withTime = false): ?string
    {
        return self::render($value, $withTime ? 'd MMM yyyy HH:mm' : 'd MMM yyyy');
    }

    /**
     * e.g. "29 ก.ย." for chart labels.
     */
    public static function dayMonth(mixed $value): ?string
    {
        return self::render($value, 'd MMM');
    }

    /**
     * e.g. "วันศุกร์ที่ 3 ตุลาคม 2569"
     */
    public static function long(mixed $value): ?string
    {
        return self::render($value, 'EEEEที่ d MMMM yyyy');
    }

    private static function render(mixed $value, string $pattern): ?string
    {
        if (blank($value)) {
            return null;
        }

        return IntlDateFormatter::create(
            'th_TH@calendar=buddhist',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Asia/Bangkok',
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        )->format(Carbon::parse($value));
    }

    /**
     * For ->formatStateUsing() on table columns and infolist entries.
     */
    public static function formatter(bool $withTime = false): Closure
    {
        return fn (mixed $state): ?string => self::format($state, $withTime);
    }

    /**
     * For ->hint() on a live DatePicker: the browser picker shows ค.ศ., so echo the choice in พ.ศ.
     */
    public static function hint(): Closure
    {
        return fn (?string $state): ?string => filled($state) ? 'พ.ศ. '.self::format($state) : null;
    }
}
