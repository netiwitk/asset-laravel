<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetActions;
use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...AssetActions::all(),
            EditAction::make(),
            // Shown disabled with AssetPolicy's reason, e.g. when the tag was reused.
            RestoreAction::make()->authorizationTooltip(),
        ];
    }
}
