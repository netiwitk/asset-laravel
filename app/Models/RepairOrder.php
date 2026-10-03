<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['asset_id', 'requested_by', 'vendor', 'sent_on', 'expected_return_on', 'finished_on', 'cost', 'result_note'])]
class RepairOrder extends Model
{
    protected function casts(): array
    {
        return [
            'sent_on' => 'date',
            'expected_return_on' => 'date',
            'finished_on' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereNull('finished_on')->whereDate('expected_return_on', '<', today());
    }

    public function isOverdue(): bool
    {
        return $this->finished_on === null && (bool) $this->expected_return_on?->lt(today());
    }
}
