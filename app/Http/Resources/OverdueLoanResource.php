<?php

namespace App\Http\Resources;

use App\Filament\ThaiDate;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Loan
 */
class OverdueLoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tag' => $this->asset->asset_tag,
            'name' => $this->asset->name,
            'borrower' => $this->borrower->name,
            'due_on' => ThaiDate::format($this->due_on),
            'days_overdue' => (int) $this->due_on->diffInDays(today()),
        ];
    }
}
