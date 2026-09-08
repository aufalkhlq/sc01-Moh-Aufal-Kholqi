<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\User;

trait ResolvesActiveWebUser
{
    /**
     * Resolves the active user identity from the session, falling back to the first user or creating one.
     */
    protected function getActiveUser(): User
    {
        $userId = session('active_user_id');

        if ($userId && ($user = User::find($userId))) {
            return $user;
        }

        $firstUser = User::first();
        if ($firstUser) {
            session(['active_user_id' => $firstUser->id]);

            return $firstUser;
        }

        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('password123'),
            ]
        );

        session(['active_user_id' => $user->id]);

        return $user;
    }
}
