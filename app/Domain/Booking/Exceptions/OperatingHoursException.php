<?php

namespace App\Domain\Booking\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperatingHoursException extends Exception
{
    protected array $details;

    public function __construct(
        string $message = 'The requested booking time falls outside of the room operating hours.',
        array $details = []
    ) {
        parent::__construct($message, 422);
        $this->details = $details;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'error_code' => 'OUTSIDE_OPERATING_HOURS',
            'message' => $this->getMessage(),
            'details' => $this->details,
        ], 422);
    }
}
