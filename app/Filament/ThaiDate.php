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
        if (blank($value)) {
            return null;
        }

        return IntlDateFormatter::create(
            'th_TH@calendar=buddhist',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'Asia/Bangkok',
            IntlDateFormatter::TRADITIONAL,
            $withTime ? 'd MMM yyyy HH:mm' : 'd MMM yyyy',
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
