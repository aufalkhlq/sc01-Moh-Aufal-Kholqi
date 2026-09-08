<?php

use App\Domain\Booking\Exceptions\OperatingHoursException;
use App\Domain\Booking\Exceptions\RoomInactiveException;
use App\Domain\Booking\Exceptions\ScheduleConflictException;
use App\Domain\Booking\Exceptions\UnauthorizedBookingException;
use App\Http\Middleware\IdentifyUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'identify.user' => IdentifyUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ScheduleConflictException $e, Request $request) {
            return $e->render($request);
        });

        $exceptions->render(function (UnauthorizedBookingException $e, Request $request) {
            return $e->render($request);
        });

        $exceptions->render(function (OperatingHoursException $e, Request $request) {
            return $e->render($request);
        });

        $exceptions->render(function (RoomInactiveException $e, Request $request) {
            return $e->render($request);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            if (($request->is('api/*') || $request->expectsJson()) && str_contains($e->getMessage(), 'DB_STORAGE_CONSTRAINT')) {
                return response()->json([
                    'status' => 'error',
                    'error_code' => 'SCHEDULE_CONFLICT',
                    'message' => 'Database storage constraint violation: Room is already booked for this time range.',
                ], 409);
            }
        });
    })->create();
