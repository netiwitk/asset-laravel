<?php

namespace App\Filament\Pages;

use App\Filament\ThaiDate;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    public function getHeading(): string|Htmlable|null
    {
        return 'สวัสดี, '.auth()->user()->name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $user = auth()->user();

        return collect([ThaiDate::long(now()), $user->role->getLabel(), $user->department?->name])
            ->filter()
            ->implode(' · ');
    }
}
