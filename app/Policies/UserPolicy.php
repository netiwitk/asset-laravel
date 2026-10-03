<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * User management is admin-only. Users are never deleted: ledger rows and loans point at them.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        // Demo visitors share the one-click accounts; editing them would break the demo for everyone.
        return $model->isDemoLogin()
            ? Response::deny('บัญชีทดลองแก้ไขไม่ได้ในโหมด demo')
            : Response::allow();
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
