<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyUser
{
    /**
     * Handle an incoming request.
     * Identifies the client user via 'X-User-Id' header or authenticated session.
     */
    public function handle(Request $request, Closure $next, bool $required = true): Response
    {
        $userIdHeader = $request->header('X-User-Id');
        $userId = null;

        if ($userIdHeader !== null && is_numeric($userIdHeader)) {
            $userId = (int) $userIdHeader;
            // Ensure a corresponding user row exists in DB for foreign key integrity
            User::query()->firstOrCreate(
                ['id' => $userId],
                [
                    'name' => "User {$userId}",
                    'email' => "user{$userId}@example.com",
                    'password' => bcrypt('secret123'),
                ]
            );
        } elseif ($request->user() !== null) {
            $userId = $request->user()->id;
        }

        if ($required && $userId === null) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'User identification is required. Please provide the X-User-Id header.',
            ], 401);
        }

        if ($userId !== null) {
            $request->attributes->set('current_user_id', $userId);
        }

        return $next($request);
    }
}
