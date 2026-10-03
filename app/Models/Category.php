<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'useful_life_years'])]
class Category extends Model
{
    use HasFactory;

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
