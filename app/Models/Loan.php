<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** status and the timestamps after requested_at change only through App\Services\AssetLedger. */
#[Fillable(['asset_id', 'requester_id', 'borrower_id', 'requested_at', 'due_on', 'purpose'])]
class Loan extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'due_on' => 'date',
            'returned_on' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Handed over and the due date has passed (today is still on time).
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', LoanStatus::HandedOver)->whereDate('due_on', '<', today());
    }

    public function isOverdue(): bool
    {
        return $this->status === LoanStatus::HandedOver && (bool) $this->due_on?->lt(today());
    }
}
