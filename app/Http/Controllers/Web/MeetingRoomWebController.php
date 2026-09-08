<?php

namespace App\Http\Controllers\Web;

use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Services\RoomBookingService;
use App\Domain\Booking\UseCases\SetOperatingHoursUseCase;
use App\Domain\Booking\UseCases\UpdateRoomUseCase;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ResolvesActiveWebUser;
use App\Http\Requests\SetOperatingHoursRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingRoomWebController extends Controller
{
    use ResolvesActiveWebUser;

    /**
     * Display list of meeting rooms with filters.
     */
    public function index(Request $request): View
    {
        $query = Room::query()->with('operatingHours');

        if ($request->filled('min_capacity')) {
            $query->where('capacity', '>=', (int) $request->query('min_capacity'));
        }

        if ($request->has('is_active') && $request->query('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $data = [
            'rooms' => $query->orderBy('id')->get(),
            'activeUser' => $this->getActiveUser(),
            'users' => User::orderBy('id')->get(),
        ];

        return view('rooms.index', $data);
    }

    /**
     * Display detailed room schedule and booking creation form.
     */
    public function show(Request $request, Room $room): View
    {
        $room->load('operatingHours');

        $query = Booking::query()
            ->where('room_id', $room->id)
            ->with(['user', 'histories']);

        $dateFilter = $request->query('date');
        $startDateFilter = $request->query('start_date');
        $endDateFilter = $request->query('end_date');

        if ($dateFilter) {
            $start = Carbon::parse($dateFilter)->startOfDay()->utc();
            $end = Carbon::parse($dateFilter)->endOfDay()->utc();
            $query->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<=', $end)
                    ->where('end_time', '>=', $start);
            });
        } elseif ($startDateFilter && $endDateFilter) {
            $start = Carbon::parse($startDateFilter)->startOfDay()->utc();
            $end = Carbon::parse($endDateFilter)->endOfDay()->utc();
            $query->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<=', $end)
                    ->where('end_time', '>=', $start);
            });
        }

        $allConfirmedBookings = Booking::query()
            ->where('room_id', $room->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->with('user')
            ->orderBy('start_time', 'asc')
            ->get();

        $data = [
            'room' => $room,
            'bookings' => $query->orderBy('start_time', 'asc')->get(),
            'allConfirmedBookings' => $allConfirmedBookings,
            'activeUser' => $this->getActiveUser(),
            'users' => User::orderBy('id')->get(),
            'dateFilter' => $dateFilter,
            'startDateFilter' => $startDateFilter,
            'endDateFilter' => $endDateFilter,
        ];

        return view('rooms.show', $data);
    }

    /**
     * Store a new meeting room.
     */
    public function storeRoom(StoreRoomRequest $request): RedirectResponse
    {
        $room = Room::create($request->validated());

        return redirect()->route('web.rooms.index')
            ->with('success', "Ruang meeting '{$room->name}' berhasil dibuat.");
    }

    /**
     * Update an existing meeting room.
     */
    public function updateRoom(UpdateRoomRequest $request, Room $room, UpdateRoomUseCase $useCase): RedirectResponse
    {
        $data = $request->validated();
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $useCase->execute($room, $data);

        return redirect()->back()
            ->with('success', "Data ruang '{$room->name}' berhasil diperbarui.");
    }

    /**
     * Delete a meeting room.
     */
    public function destroyRoom(Room $room): RedirectResponse
    {
        $name = $room->name;
        $room->delete();

        return redirect()->route('web.rooms.index')
            ->with('success', "Ruang '{$name}' telah dihapus.");
    }

    /**
     * Configure operating hours for a room.
     */
    public function setOperatingHours(SetOperatingHoursRequest $request, Room $room, SetOperatingHoursUseCase $useCase): RedirectResponse
    {
        $hours = $request->validated()['hours'] ?? [];
        $useCase->execute($room, $hours);

        $msg = empty($hours)
            ? "Jam operasional '{$room->name}' direset (beroperasi 24 Jam penuh)."
            : "Jam operasional '{$room->name}' berhasil diperbarui (".count($hours).' hari aktif).';

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Search available rooms within a time window (single or recurring series).
     */
    public function searchAvailable(Request $request, RoomBookingService $bookingService): View
    {
        $searchType = $request->query('search_type', 'single');
        $minCapacity = $request->filled('min_capacity') ? max(1, (int) $request->query('min_capacity')) : null;

        $startTime = $request->query('start_time');
        $endTime = $request->query('end_time');

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $startTimeOfDay = $request->query('start_time_of_day');
        $endTimeOfDay = $request->query('end_time_of_day');
        $frequency = $request->query('frequency', 'daily');

        $availableRooms = collect();
        $hasSearched = false;

        if ($searchType === 'recurring' || ($startDate && $endDate && $startTimeOfDay && $endTimeOfDay)) {
            $searchType = 'recurring';
            if ($startDate && $endDate && $startTimeOfDay && $endTimeOfDay) {
                try {
                    if ($startDate > $endDate) {
                        throw new \InvalidArgumentException('Tanggal selesai harus sama atau setelah tanggal mulai.');
                    }
                    if ($startTimeOfDay >= $endTimeOfDay) {
                        throw new \InvalidArgumentException('Jam selesai harian harus setelah jam mulai harian.');
                    }

                    $availableRooms = $bookingService->searchAvailableRoomsRecurring(
                        startDate: $startDate,
                        endDate: $endDate,
                        startTimeOfDay: $startTimeOfDay,
                        endTimeOfDay: $endTimeOfDay,
                        frequency: $frequency,
                        exceptionDates: [],
                        timezone: 'UTC',
                        minCapacity: $minCapacity ?? 1
                    );
                    $hasSearched = true;
                } catch (\InvalidArgumentException $e) {
                    session()->flash('error', $e->getMessage());
                } catch (\Throwable $e) {
                    session()->flash('error', 'Format pencarian berulang tidak valid: '.$e->getMessage());
                }
            } elseif ($request->hasAny(['start_date', 'end_date', 'start_time_of_day', 'end_time_of_day'])) {
                session()->flash('error', 'Mohon lengkapi seluruh parameter tanggal dan jam harian untuk pencarian berulang.');
            }
        } elseif ($startTime && $endTime) {
            try {
                $startCarbon = Carbon::parse($startTime);
                $endCarbon = Carbon::parse($endTime);

                // Multi-day with daily hours pattern -> treat as recurring
                if ($startCarbon->format('Y-m-d') !== $endCarbon->format('Y-m-d') && $startCarbon->format('H:i') < $endCarbon->format('H:i')) {
                    $searchType = 'recurring';
                    $startDate = $startCarbon->format('Y-m-d');
                    $endDate = $endCarbon->format('Y-m-d');
                    $startTimeOfDay = $startCarbon->format('H:i');
                    $endTimeOfDay = $endCarbon->format('H:i');
                    $frequency = 'daily';

                    $availableRooms = $bookingService->searchAvailableRoomsRecurring(
                        startDate: $startDate,
                        endDate: $endDate,
                        startTimeOfDay: $startTimeOfDay,
                        endTimeOfDay: $endTimeOfDay,
                        frequency: 'daily',
                        exceptionDates: [],
                        timezone: 'UTC',
                        minCapacity: $minCapacity ?? 1
                    );
                    $hasSearched = true;
                } else {
                    $range = new TimeRange($startTime, $endTime);
                    $availableRooms = $bookingService->searchAvailableRooms($range, $minCapacity ?? 1);
                    $hasSearched = true;
                }
            } catch (\InvalidArgumentException $e) {
                session()->flash('error', 'Rentang waktu tidak valid: Waktu selesai harus setelah waktu mulai.');
            } catch (\Throwable $e) {
                session()->flash('error', 'Format waktu tidak valid: '.$e->getMessage());
            }
        } elseif ($request->has('start_time') || $request->has('end_time')) {
            session()->flash('error', 'Mohon lengkapi kedua parameter Waktu Mulai dan Waktu Selesai.');
        }

        $data = [
            'availableRooms' => $availableRooms,
            'hasSearched' => $hasSearched,
            'searchType' => $searchType,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'startTimeOfDay' => $startTimeOfDay,
            'endTimeOfDay' => $endTimeOfDay,
            'frequency' => $frequency,
            'minCapacity' => $minCapacity,
            'activeUser' => $this->getActiveUser(),
            'users' => User::orderBy('id')->get(),
        ];

        return view('rooms.search', $data);
    }

    /*
    |--------------------------------------------------------------------------
    | Backward Compatibility Proxies for Booking & User Operations
    |--------------------------------------------------------------------------
    */

    public function storeBooking(Request $request, RoomBookingService $bookingService): RedirectResponse
    {
        return app(BookingWebController::class)->store($request, $bookingService);
    }

    public function cancelBooking(Request $request, Booking $booking, RoomBookingService $bookingService): RedirectResponse
    {
        return app(BookingWebController::class)->cancel($request, $booking, $bookingService);
    }

    public function rescheduleBooking(Request $request, Booking $booking, RoomBookingService $bookingService): RedirectResponse
    {
        return app(BookingWebController::class)->reschedule($request, $booking, $bookingService);
    }

    public function myBookings(Request $request): View
    {
        return app(BookingWebController::class)->myBookings($request);
    }

    public function switchUser(Request $request): RedirectResponse
    {
        return app(UserSwitchWebController::class)->switchUser($request);
    }
}
