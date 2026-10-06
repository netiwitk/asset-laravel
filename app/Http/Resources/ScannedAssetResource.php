<?php

namespace App\Http\Resources;

use App\Enums\LoanAction;
use App\Filament\ThaiDate;
use App\Models\Asset;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the scanner app shows, already labelled in Thai, plus the buttons this user may press.
 * The app draws what it is given and never decides a rule itself.
 *
 * @mixin Asset
 */
class ScannedAssetResource extends JsonResource
{
    /** The loan steps that make sense at the counter with the item in hand. */
    private const SCANNER_ACTIONS = [LoanAction::HandOver, LoanAction::ReceiveReturn];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $loan = $this->activeLoan;
        $labelled = fn (BackedEnum&HasLabel $state): array => ['value' => $state->value, 'label' => $state->getLabel()];

        return [
            'tag' => $this->asset_tag,
            'name' => $this->name,
            'category' => $this->category->name,
            'department' => $this->department->name,
            'location' => $this->location_note,
            'custodian' => $this->custodian?->name,
            'condition' => $labelled($this->condition),
            'availability' => $labelled($this->availability),
            'book_value' => $this->bookValue(),
            'loan' => $loan === null ? null : [
                'status' => $labelled($loan->status),
                'borrower' => $loan->borrower->name,
                'due_on' => ThaiDate::format($loan->due_on),
                'is_overdue' => $loan->isOverdue(),
                'purpose' => $loan->purpose,
            ],
            'actions' => $loan === null ? [] : array_values(array_map(
                fn (LoanAction $action): string => $action->value,
                array_filter(self::SCANNER_ACTIONS, fn (LoanAction $action): bool => $action->allows($request->user(), $loan)),
            )),
        ];
    }
}
