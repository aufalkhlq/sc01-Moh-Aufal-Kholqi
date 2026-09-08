<?php

namespace App\Http\Controllers\Api;

use App\Domain\Booking\DTOs\BookingDTO;
use App\Domain\Booking\DTOs\RecurringBookingDTO;
use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Services\RoomBookingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\RescheduleBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingHistoryResource;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    /**
     * List bookings for a room with date or date range filter.
     */
    public function index(Request $request, Room $room): AnonymousResourceCollection
    {
        $timezone = $request->query('timezone', 'UTC');

        $query = Booking::query()
            ->where('room_id', $room->id)
            ->with(['room', 'user']);

        if ($request->filled('date')) {
            $dayStart = Carbon::parse($request->query('date'), $timezone)->startOfDay()->utc();
            $dayEnd = Carbon::parse($request->query('date'), $timezone)->endOfDay()->utc();

            $query->where(function ($q) use ($dayStart, $dayEnd) {
                $q->where('start_time', '<=', $dayEnd)
                    ->where('end_time', '>=', $dayStart);
            });
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $rangeStart = Carbon::parse($request->query('start_date'), $timezone)->startOfDay()->utc();
            $rangeEnd = Carbon::parse($request->query('end_date'), $timezone)->endOfDay()->utc();

            $query->where(function ($q) use ($rangeStart, $rangeEnd) {
                $q->where('start_time', '<=', $rangeEnd)
                    ->where('end_time', '>=', $rangeStart);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $bookings = $query->orderBy('start_time', 'asc')->get();

        return BookingResource::collection($bookings);
    }

    /**
     * Create a single or recurring booking.
     */
    public function store(StoreBookingRequest $request, RoomBookingService $bookingService): JsonResponse
    {
        $userId = (int) ($request->attributes->get('current_user_id') ?? $request->header('X-User-Id'));
        $timezone = $request->input('timezone', 'UTC');

        if ($request->boolean('is_recurring')) {
            $dto = new RecurringBookingDTO(
                roomId: (int) $request->input('room_id'),
                userId: $userId,
                title: $request->input('title'),
                startTimeOfDay: $request->input('start_time_of_day'),
                endTimeOfDay: $request->input('end_time_of_day'),
                frequency: $request->input('frequency'),
                startDate: $request->input('start_date'),
                endDate: $request->input('end_date'),
                exceptionDates: $request->input('exception_dates', []),
                timezone: $timezone
            );

            $result = $bookingService->createRecurringBooking($dto);

            $collection = new Collection($result['bookings']);
            $collection->load(['room', 'user']);

            return response()->json([
                'status' => 'success',
                'message' => 'Recurring bookings created successfully.',
                'recurrence_rule_id' => $result['rule']->id,
                'count' => count($result['bookings']),
                'data' => BookingResource::collection($collection),
            ], 201);
        }

        $start = Carbon::parse($request->input('start_time'), $timezone)->utc();
        $end = Carbon::parse($request->input('end_time'), $timezone)->utc();

        $timeRange = new TimeRange($start, $end);

        $dto = new BookingDTO(
            roomId: (int) $request->input('room_id'),
            userId: $userId,
            title: $request->input('title'),
            timeRange: $timeRange
        );

        $booking = $bookingService->createBooking($dto);

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * View booking detail.
     */
    public function show(Booking $booking): BookingResource
    {
        $booking->load(['room', 'user']);

        return new BookingResource($booking);
    }

    /**
     * Cancel a booking (Authorization: only owner can cancel).
     */
    public function destroy(CancelBookingRequest $request, Booking $booking, RoomBookingService $bookingService): BookingResource
    {
        $userId = (int) ($request->attributes->get('current_user_id') ?? $request->header('X-User-Id'));

        $cancelledBooking = $bookingService->cancelBooking(
            bookingId: $booking->id,
            userId: $userId,
            reason: $request->input('reason')
        );

        return new BookingResource($cancelledBooking);
    }

    /**
     * Reschedule a booking to a new time range.
     */
    public function reschedule(RescheduleBookingRequest $request, Booking $booking, RoomBookingService $bookingService): BookingResource
    {
        $userId = (int) ($request->attributes->get('current_user_id') ?? $request->header('X-User-Id'));
        $timezone = $request->input('timezone', 'UTC');

        $start = Carbon::parse($request->input('start_time'), $timezone)->utc();
        $end = Carbon::parse($request->input('end_time'), $timezone)->utc();

        $newRange = new TimeRange($start, $end);

        $updatedBooking = $bookingService->rescheduleBooking(
            bookingId: $booking->id,
            userId: $userId,
            newRange: $newRange,
            reason: $request->input('reason')
        );

        return new BookingResource($updatedBooking);
    }

    /**
     * View audit trail / change history of a booking.
     */
    public function history(Booking $booking): AnonymousResourceCollection
    {
        $histories = $booking->histories()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return BookingHistoryResource::collection($histories);
    }

    /**
     * View all bookings belonging to the authenticated user.
     */
    public function myBookings(Request $request): AnonymousResourceCollection
    {
        $userId = (int) $request->attributes->get('auth_user_id', $request->header('X-User-Id'));

        $query = Booking::query()
            ->where('user_id', $userId)
            ->with(['room', 'user'])
            ->orderBy('start_time', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return BookingResource::collection($query->get());
    }
}
