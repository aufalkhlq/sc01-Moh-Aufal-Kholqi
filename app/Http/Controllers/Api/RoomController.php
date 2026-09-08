<?php

namespace App\Http\Controllers\Api;

use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Services\RoomBookingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableRoomsRequest;
use App\Http\Requests\SetOperatingHoursRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoomController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $rooms = Room::query()
            ->when($request->filled('min_capacity'), fn ($q) => $q->where('capacity', '>=', (int) $request->query('min_capacity')))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->with(['operatingHours'])
            ->get();

        return RoomResource::collection($rooms);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        return (new RoomResource($room))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Room $room): RoomResource
    {
        $room->load('operatingHours');

        return new RoomResource($room);
    }

    public function update(UpdateRoomRequest $request, Room $room): RoomResource
    {
        $room->update($request->validated());

        return new RoomResource($room);
    }

    public function destroy(Room $room): JsonResponse
    {
        $room->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Room '{$room->name}' has been deleted.",
        ]);
    }

    /**
     * Search available rooms within a time window with min capacity requirement.
     */
    public function available(AvailableRoomsRequest $request, RoomBookingService $bookingService): AnonymousResourceCollection
    {
        $timezone = $request->query('timezone', 'UTC');
        $minCapacity = $request->filled('min_capacity') ? max(1, (int) $request->query('min_capacity')) : 1;

        if ($request->query('search_type') === 'recurring' || $request->filled('start_date')) {
            $availableRooms = $bookingService->searchAvailableRoomsRecurring(
                startDate: (string) $request->input('start_date'),
                endDate: (string) $request->input('end_date'),
                startTimeOfDay: (string) $request->input('start_time_of_day'),
                endTimeOfDay: (string) $request->input('end_time_of_day'),
                frequency: (string) $request->input('frequency', 'daily'),
                timezone: $timezone,
                minCapacity: $minCapacity
            );

            return RoomResource::collection($availableRooms);
        }

        $start = Carbon::parse($request->input('start_time'), $timezone)->utc();
        $end = Carbon::parse($request->input('end_time'), $timezone)->utc();

        // Smart Detection: If multi-day range with daily hours pattern
        if ($start->format('Y-m-d') !== $end->format('Y-m-d') && $start->format('H:i') < $end->format('H:i')) {
            $availableRooms = $bookingService->searchAvailableRoomsRecurring(
                startDate: $start->format('Y-m-d'),
                endDate: $end->format('Y-m-d'),
                startTimeOfDay: $start->format('H:i'),
                endTimeOfDay: $end->format('H:i'),
                frequency: 'daily',
                timezone: $timezone,
                minCapacity: $minCapacity
            );

            return RoomResource::collection($availableRooms);
        }

        $range = new TimeRange($start, $end);
        $minCapacity = (int) $request->query('min_capacity', 1);

        $availableRooms = $bookingService->searchAvailableRooms($range, $minCapacity);

        return RoomResource::collection($availableRooms);
    }

    /**
     * Set or update operating hours for a room.
     */
    public function setOperatingHours(SetOperatingHoursRequest $request, Room $room): JsonResponse
    {
        $hoursData = $request->validated()['hours'];

        // Replace or update operating hours
        RoomOperatingHour::where('room_id', $room->id)->delete();

        foreach ($hoursData as $item) {
            RoomOperatingHour::create([
                'room_id' => $room->id,
                'day_of_week' => $item['day_of_week'],
                'open_time' => $item['open_time'],
                'close_time' => $item['close_time'],
            ]);
        }

        $room->load('operatingHours');

        return (new RoomResource($room))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Get list of occupied time slots and operating hours for a specific date.
     */
    public function occupiedSlots(Request $request, Room $room): JsonResponse
    {
        $timezone = $request->query('timezone', 'UTC');
        $date = $request->query('date', now($timezone)->format('Y-m-d'));

        $startOfDay = Carbon::parse($date, $timezone)->startOfDay()->utc();
        $endOfDay = Carbon::parse($date, $timezone)->endOfDay()->utc();

        $bookings = Booking::query()
            ->where('room_id', $room->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where(function ($q) use ($startOfDay, $endOfDay) {
                $q->where('start_time', '<=', $endOfDay)
                    ->where('end_time', '>=', $startOfDay);
            })
            ->with('user')
            ->orderBy('start_time', 'asc')
            ->get();

        $dayOfWeek = Carbon::parse($date, $timezone)->dayOfWeek;
        $operatingHour = $room->operatingHours()->where('day_of_week', $dayOfWeek)->first();

        return response()->json([
            'room_id' => $room->id,
            'room_name' => $room->name,
            'buffer_minutes' => $room->buffer_minutes,
            'date' => $date,
            'operating_hours' => $operatingHour ? [
                'open_time' => $operatingHour->open_time,
                'close_time' => $operatingHour->close_time,
            ] : null,
            'occupied_slots' => $bookings->map(function (Booking $b) use ($room, $timezone) {
                $startLocal = Carbon::parse($b->start_time)->setTimezone($timezone);
                $endLocal = Carbon::parse($b->end_time)->setTimezone($timezone);
                $effectiveEndLocal = $endLocal->copy()->addMinutes($room->buffer_minutes);

                return [
                    'booking_id' => $b->id,
                    'title' => $b->title,
                    'user_name' => $b->user?->name ?? "User #{$b->user_id}",
                    'start_time' => $b->start_time->toIso8601String(),
                    'end_time' => $b->end_time->toIso8601String(),
                    'start_time_local' => $startLocal->format('H:i'),
                    'end_time_local' => $endLocal->format('H:i'),
                    'effective_end_time_local' => $effectiveEndLocal->format('H:i'),
                    'duration_minutes' => (int) $b->start_time->diffInMinutes($b->end_time),
                ];
            }),
        ]);
    }
}
