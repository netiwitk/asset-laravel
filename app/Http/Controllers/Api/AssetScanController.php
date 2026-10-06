<?php

namespace App\Http\Controllers\Api;

use App\Enums\Condition;
use App\Enums\LoanAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScannedAssetResource;
use App\Models\Asset;
use App\Models\Loan;
use App\Services\AssetLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What the scanner app does after reading a QR code. Every change goes through AssetLedger,
 * and the buttons it may show come from the same LoanAction rules as the web.
 */
class AssetScanController extends Controller
{
    public function show(Request $request, string $tag): ScannedAssetResource
    {
        return new ScannedAssetResource($this->find($request, $tag));
    }

    public function handOver(Request $request, string $tag): ScannedAssetResource
    {
        AssetLedger::handOver($this->loanFor($request, $tag, LoanAction::HandOver), $request->user());

        return new ScannedAssetResource($this->find($request, $tag));
    }

    public function receiveReturn(Request $request, string $tag): ScannedAssetResource
    {
        $loan = $this->loanFor($request, $tag, LoanAction::ReceiveReturn);
        $data = $request->validate([
            'condition' => ['required', Rule::enum(Condition::class)->only([Condition::Usable, Condition::Damaged])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        AssetLedger::receiveReturn($loan, $request->user(), Condition::from($data['condition']), $data['note'] ?? null);

        return new ScannedAssetResource($this->find($request, $tag));
    }

    /**
     * Another department's asset is a 404, not a 403, so staff cannot learn which tags exist.
     */
    private function find(Request $request, string $tag): Asset
    {
        return Asset::query()
            ->visibleTo($request->user())
            ->with(['category', 'department', 'custodian', 'activeLoan.borrower'])
            ->where('asset_tag', $tag)
            ->firstOrFail();
    }

    private function loanFor(Request $request, string $tag, LoanAction $action): Loan
    {
        $loan = $this->find($request, $tag)->activeLoan;

        abort_unless($loan !== null && $action->allows($request->user(), $loan), 403, 'คุณทำรายการนี้กับทรัพย์สินชิ้นนี้ไม่ได้');

        return $loan;
    }
}
