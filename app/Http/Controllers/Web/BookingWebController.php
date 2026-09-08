<?php

namespace App\Http\Controllers\Web;

use App\Domain\Booking\DTOs\BookingDTO;
use App\Domain\Booking\DTOs\RecurringBookingDTO;
use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Exceptions\OperatingHoursException;
use App\Domain\Booking\Exceptions\RoomInactiveException;
use App\Domain\Booking\Exceptions\ScheduleConflictException;
use App\Domain\Booking\Exceptions\UnauthorizedBookingException;
use App\Domain\Booking\Services\RoomBookingService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ResolvesActiveWebUser;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingWebController extends Controller
{
    use ResolvesActiveWebUser;

    /**
     * Store a new booking (single or recurring series).
     */
    public function store(Request $request, RoomBookingService $bookingService): RedirectResponse
    {
        $activeUser = $this->getActiveUser();

        $rules = [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'is_recurring' => ['sometimes', 'boolean'],
        ];

        if ($request->boolean('is_recurring')) {
            $rules['frequency'] = ['required', 'in:daily,weekly'];
            $rules['start_date'] = ['required', 'date_format:Y-m-d'];
            $rules['end_date'] = ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'];
            $rules['start_time_of_day'] = ['required', 'string'];
            $rules['end_time_of_day'] = ['required', 'string'];
            $rules['exception_dates'] = ['sometimes', 'nullable', 'string'];
        } else {
            $rules['start_time'] = ['required', 'date'];
            $rules['end_time'] = ['required', 'date', 'after:start_time'];
        }

        $validated = $request->validate($rules, [
            'end_time.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);

        try {
            if ($request->boolean('is_recurring')) {
                $exceptionDates = [];
                if (! empty($validated['exception_dates'])) {
                    $exceptionDates = array_map('trim', explode(',', $validated['exception_dates']));
                }

                $dto = new RecurringBookingDTO(
                    roomId: (int) $validated['room_id'],
                    userId: $activeUser->id,
                    title: $validated['title'],
                    startTimeOfDay: $validated['start_time_of_day'],
                    endTimeOfDay: $validated['end_time_of_day'],
                    frequency: $validated['frequency'],
                    startDate: $validated['start_date'],
                    endDate: $validated['end_date'],
                    exceptionDates: $exceptionDates,
                    timezone: 'UTC'
                );

                $result = $bookingService->createRecurringBooking($dto);
                $count = count($result['bookings']);

                return redirect()->back()
                    ->with('success', "Berhasil membuat {$count} booking berulang ({$dto->frequency}).");
            }

            $timeRange = new TimeRange($validated['start_time'], $validated['end_time']);

            $dto = new BookingDTO(
                roomId: (int) $validated['room_id'],
                userId: $activeUser->id,
                title: $validated['title'],
                timeRange: $timeRange
            );

            $booking = $bookingService->createBooking($dto);

            return redirect()->back()
                ->with('success', "Booking '{$booking->title}' berhasil dibuat!");
        } catch (ScheduleConflictException $e) {
            $conflictingDate = isset($validated['start_time'])
                ? Carbon::parse($validated['start_time'])->format('Y-m-d')
                : ($validated['start_date'] ?? now()->format('Y-m-d'));

            $dayBookings = Booking::query()
                ->where('room_id', $validated['room_id'])
                ->where('status', Booking::STATUS_CONFIRMED)
                ->whereDate('start_time', $conflictingDate)
                ->with('user')
                ->orderBy('start_time', 'asc')
                ->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'user_name' => $b->user?->name ?? "User #{$b->user_id}",
                    'start_formatted' => $b->start_time->format('H:i'),
                    'end_formatted' => $b->end_time->format('H:i'),
                ])
                ->values()
                ->toArray();

            return redirect()->back()
                ->withInput()
                ->with('conflict_error', $e->getMessage())
                ->with('conflict_details', $e->getConflictDetails())
                ->with('occupied_slots_today', $dayBookings)
                ->with('conflicting_date', $conflictingDate);
        } catch (OperatingHoursException|RoomInactiveException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Cancel an existing booking with authorization check.
     */
    public function cancel(Request $request, Booking $booking, RoomBookingService $bookingService): RedirectResponse
    {
        $activeUser = $this->getActiveUser();
        $reason = $request->input('reason', 'Dibatalkan oleh pengguna melalui web.');

        try {
            $bookingService->cancelBooking($booking->id, $activeUser->id, $reason);

            return redirect()->back()
                ->with('success', "Booking '{$booking->title}' berhasil dibatalkan.");
        } catch (UnauthorizedBookingException $e) {
            return redirect()->back()
                ->with('error', 'Gagal membatalkan: Anda bukan pemilik booking ini! (Otorisasi ditolak)');
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('error', 'Gagal membatalkan booking: '.$e->getMessage());
        }
    }

    /**
     * Reschedule an existing booking with authorization check.
     */
    public function reschedule(Request $request, Booking $booking, RoomBookingService $bookingService): RedirectResponse
    {
        $activeUser = $this->getActiveUser();

        $request->validate([
            'reschedule_start' => ['required', 'date'],
            'reschedule_end' => ['required', 'date', 'after:reschedule_start'],
            'reschedule_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], [
            'reschedule_end.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);

        try {
            $newRange = new TimeRange($request->input('reschedule_start'), $request->input('reschedule_end'));

            $bookingService->rescheduleBooking(
                bookingId: $booking->id,
                userId: $activeUser->id,
                newRange: $newRange,
                reason: $request->input('reschedule_reason')
            );

            return redirect()->back()
                ->with('success', "Jadwal booking '{$booking->title}' berhasil diperbarui.");
        } catch (UnauthorizedBookingException $e) {
            return redirect()->back()
                ->with('error', 'Gagal reschedule: Anda bukan pemilik booking ini! (Otorisasi ditolak)');
        } catch (ScheduleConflictException $e) {
            return redirect()->back()
                ->with('conflict_error', 'Jadwal baru bertabrakan dengan booking lain: '.$e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('error', 'Gagal reschedule: '.$e->getMessage());
        }
    }

    /**
     * Display rooms and bookings reserved by the active user.
     */
    public function myBookings(Request $request): View
    {
        $activeUser = $this->getActiveUser();
        $statusFilter = $request->query('status', 'all');

        $query = Booking::query()
            ->where('user_id', $activeUser->id)
            ->with(['room', 'histories.user'])
            ->orderBy('start_time', 'desc');

        if ($statusFilter === 'confirmed') {
            $query->where('status', Booking::STATUS_CONFIRMED);
        } elseif ($statusFilter === 'cancelled') {
            $query->where('status', Booking::STATUS_CANCELLED);
        } elseif ($statusFilter === 'upcoming') {
            $query->where('status', Booking::STATUS_CONFIRMED)
                ->where('end_time', '>=', now());
        }

        $bookings = $query->get();

        $totalCount = Booking::where('user_id', $activeUser->id)->count();
        $confirmedCount = Booking::where('user_id', $activeUser->id)->where('status', Booking::STATUS_CONFIRMED)->count();
        $upcomingCount = Booking::where('user_id', $activeUser->id)->where('status', Booking::STATUS_CONFIRMED)->where('end_time', '>=', now())->count();
        $cancelledCount = Booking::where('user_id', $activeUser->id)->where('status', Booking::STATUS_CANCELLED)->count();

        $data = [
            'activeUser' => $activeUser,
            'users' => User::orderBy('id')->get(),
            'bookings' => $bookings,
            'statusFilter' => $statusFilter,
            'totalCount' => $totalCount,
            'confirmedCount' => $confirmedCount,
            'upcomingCount' => $upcomingCount,
            'cancelledCount' => $cancelledCount,
        ];

        return view('rooms.my_bookings', $data);
    }
}
