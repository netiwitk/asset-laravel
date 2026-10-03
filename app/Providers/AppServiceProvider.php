<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Plain CSS on Filament's hook classes; `php artisan filament:assets` publishes it to public/css/app.
        FilamentAsset::register([
            Css::make('asset-theme', resource_path('css/filament/asset-theme.css')),
        ]);

        // Livewire skips Laravel's TrimStrings middleware, so trim form text here (never passwords).
        $trim = fn (TextInput|Textarea $component, mixed $state): mixed => is_string($state) && ! ($component instanceof TextInput && $component->isPassword())
            ? trim($state)
            : $state;
        TextInput::configureUsing(fn (TextInput $input) => $input->dehydrateStateUsing($trim));
        Textarea::configureUsing(fn (Textarea $textarea) => $textarea->dehydrateStateUsing($trim));

        // Surface N+1 queries while developing and testing; production stays lenient.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Audit trail for master data (see AuditLog). Loans and repair orders carry their own actor columns.
        foreach ([Asset::class, User::class, Category::class, Department::class] as $model) {
            foreach (['created', 'updated', 'deleted'] as $event) {
                $model::{$event}(fn (Model $record) => AuditLog::record($record, $event));
            }
        }
        Asset::restored(fn (Asset $asset) => AuditLog::record($asset, 'restored'));
    }
}
