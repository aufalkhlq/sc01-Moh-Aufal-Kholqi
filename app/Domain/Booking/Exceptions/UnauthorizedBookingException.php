<?php

namespace App\Domain\Booking\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnauthorizedBookingException extends Exception
{
    public function __construct(string $message = 'You are not authorized to perform this action on this booking.')
    {
        parent::__construct($message, 403);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'error_code' => 'FORBIDDEN',
            'message' => $this->getMessage(),
        ], 403);
    }
}
