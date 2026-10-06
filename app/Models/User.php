<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'department_id', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Accounts behind the one-click logins when APP_DEMO=true: <name>@demo.test */
    public const DEMO_LOGINS = ['admin', 'officer', 'staff'];

    /** Mirrors the column defaults so a freshly created model has them before a reload. */
    protected $attributes = [
        'role' => 'staff',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Officers and admins run the asset workflow; staff only request loans. */
    public function isOfficer(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Officer], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * In demo mode every visitor shares these accounts, so nobody may edit them.
     */
    public function isDemoLogin(): bool
    {
        return config('app.demo')
            && in_array($this->email, array_map(fn (string $login): string => "{$login}@demo.test", self::DEMO_LOGINS), true);
    }

    // Users are never deleted (ledger rows point at them forever); deactivate instead.
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }
}
