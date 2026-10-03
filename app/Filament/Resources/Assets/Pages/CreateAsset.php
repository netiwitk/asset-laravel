<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Services\AssetLedger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    /**
     * Registration goes through the ledger so the asset starts with an "opening_balance" movement.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $departmentId = (int) Arr::pull($data, 'department_id');

        return AssetLedger::register($data, $departmentId, auth()->user(), 'ลงทะเบียนใหม่');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
