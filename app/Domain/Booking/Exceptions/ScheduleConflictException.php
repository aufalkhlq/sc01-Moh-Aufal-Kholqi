<?php

namespace App\Domain\Booking\Exceptions;

use App\Models\Booking;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleConflictException extends Exception
{
    protected ?Booking $conflictingBooking;

    protected array $conflictDetails;

    public function __construct(
        string $message = 'The room is already booked for the requested time range.',
        ?Booking $conflictingBooking = null,
        array $conflictDetails = []
    ) {
        parent::__construct($message, 409);
        $this->conflictingBooking = $conflictingBooking;

        if ($conflictingBooking !== null && empty($conflictDetails)) {
            $conflictingBooking->loadMissing('user');

            $this->conflictDetails = [
                'conflicting_booking_id' => $conflictingBooking->id,
                'title' => $conflictingBooking->title,
                'user_name' => $conflictingBooking->user?->name ?? "User #{$conflictingBooking->user_id}",
                'start_time' => $conflictingBooking->start_time?->toIso8601String(),
                'end_time' => $conflictingBooking->end_time?->toIso8601String(),
                'formatted_start' => $conflictingBooking->start_time?->format('d M Y, H:i'),
                'formatted_end' => $conflictingBooking->end_time?->format('H:i'),
            ];
        } else {
            $this->conflictDetails = $conflictDetails;
        }
    }

    public function getConflictingBooking(): ?Booking
    {
        return $this->conflictingBooking;
    }

    public function getConflictDetails(): array
    {
        return $this->conflictDetails;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'error_code' => 'SCHEDULE_CONFLICT',
            'message' => $this->getMessage(),
            'conflict_details' => $this->conflictDetails,
        ], 409);
    }
}
