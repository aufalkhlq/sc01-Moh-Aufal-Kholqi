<?php

namespace App\Domain\Booking\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomInactiveException extends Exception
{
    public function __construct(string $message = 'The requested room is currently inactive or under maintenance.')
    {
        parent::__construct($message, 422);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'error_code' => 'ROOM_INACTIVE',
            'message' => $this->getMessage(),
        ], 422);
    }
}
