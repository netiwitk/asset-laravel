<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OverdueLoanResource;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Loans past their due date on assets this user may see, oldest first. The LINE bot answers "เกินกำหนด" with it.
 */
class OverdueLoanController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return OverdueLoanResource::collection(
            Loan::query()
                ->overdue()
                ->whereHas('asset', fn (Builder $query) => $query->visibleTo($request->user()))
                ->with(['asset', 'borrower'])
                ->orderBy('due_on')
                ->get(),
        );
    }
}
