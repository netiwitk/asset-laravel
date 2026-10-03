<?php

namespace App\Models;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * condition, availability and department_id are deliberately NOT fillable:
 * they change only through App\Services\AssetLedger, which writes the movement row.
 */
#[Fillable(['asset_tag', 'name', 'category_id', 'custodian_id', 'serial_no', 'acquired_on', 'cost', 'location_note'])]
class Asset extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'condition' => Condition::class,
            'availability' => Availability::class,
            'acquired_on' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function repairOrders(): HasMany
    {
        return $this->hasMany(RepairOrder::class);
    }

    /**
     * An approved loan reserves the asset even before hand-over changes its availability.
     */
    public function hasApprovedLoan(): bool
    {
        return $this->loans()->where('status', LoanStatus::Approved)->exists();
    }

    /**
     * Only idle assets may be deleted; one that is out on loan, in repair or reserved
     * would leave a loan or repair order that can never be closed.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->trashed() && $this->availability === Availability::Available && ! $this->hasApprovedLoan();
    }

    /**
     * The tag must still be free: it may have been reused after this asset was deleted.
     */
    public function canBeRestored(): bool
    {
        return $this->trashed() && ! static::query()->where('asset_tag', $this->asset_tag)->exists();
    }
}
