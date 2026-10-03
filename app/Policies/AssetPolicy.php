<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Filament asks this policy for every button (edit, delete, bulk delete, restore),
 * so the rules live here rather than in the resource. Staff visibility by department
 * is a query scope in AssetResource::getEloquentQuery().
 */
class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Asset $asset): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isOfficer();
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->isOfficer() && ! $asset->trashed();
    }

    public function delete(User $user, Asset $asset): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $asset->canBeDeleted()
            ? Response::allow()
            : Response::deny('ลบได้เฉพาะทรัพย์สินที่ว่างและไม่มีคำขอยืมที่อนุมัติแล้ว');
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Asset $asset): Response
    {
        if (! $user->isAdmin()) {
            return Response::deny();
        }

        return $asset->canBeRestored()
            ? Response::allow()
            : Response::deny('เลขครุภัณฑ์นี้ถูกใช้กับทรัพย์สินชิ้นอื่นแล้ว');
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Ledger rows reference the asset forever, so it can only be soft-deleted.
     */
    public function forceDelete(User $user, Asset $asset): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
