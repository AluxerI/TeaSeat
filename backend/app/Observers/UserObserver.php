<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
       public function created(User $user): void
    {

        if (!$user->hasAnyRole(['admin', 'manager', 'user'])) { 
            $user->assignRole('user');
        }
    }
}