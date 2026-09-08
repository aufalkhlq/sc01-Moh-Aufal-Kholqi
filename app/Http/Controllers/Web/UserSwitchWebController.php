<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserSwitchWebController extends Controller
{
    /**
     * Switch the active user identity in the session.
     */
    public function switchUser(Request $request): RedirectResponse
    {
        $userId = (int) $request->input('user_id');
        $user = User::find($userId);

        if ($user) {
            session(['active_user_id' => $user->id]);

            return redirect()->back()
                ->with('success', "Identitas pengguna aktif diubah menjadi: {$user->name} (ID: {$user->id})");
        }

        return redirect()->back()->with('error', 'Pengguna tidak ditemukan.');
    }
}
