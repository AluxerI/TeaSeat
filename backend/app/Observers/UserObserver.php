<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
       public function created(User $user): void
    {

        if (!$user->hasAnyRole(User::STAFF_ROLES) && !$user->hasRole(User::ROLE_USER)) {
            $user->assignRole(User::ROLE_USER);
        }
    }
}
