<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class BankAccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if (BouncerFacade::can('view-bank-account', BankAccount::class)) {
            return true;
        }

        return false;
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        if (BouncerFacade::can('view-bank-account', $bankAccount) && $user->hasCompany($bankAccount->company_id)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        if (BouncerFacade::can('create-bank-account', BankAccount::class)) {
            return true;
        }

        return false;
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        if (BouncerFacade::can('edit-bank-account', $bankAccount) && $user->hasCompany($bankAccount->company_id)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        if (BouncerFacade::can('delete-bank-account', $bankAccount) && $user->hasCompany($bankAccount->company_id)) {
            return true;
        }

        return false;
    }

    public function deleteMultiple(User $user): bool
    {
        if (BouncerFacade::can('delete-bank-account', BankAccount::class)) {
            return true;
        }

        return false;
    }
}
