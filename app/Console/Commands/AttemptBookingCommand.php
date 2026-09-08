<?php

namespace App\Console\Commands;

use App\Domain\Booking\DTOs\BookingDTO;
use App\Domain\Booking\DTOs\TimeRange;
use App\Domain\Booking\Exceptions\ScheduleConflictException;
use App\Domain\Booking\Services\RoomBookingService;
use Illuminate\Console\Command;

class AttemptBookingCommand extends Command
{
    protected $signature = 'booking:attempt {roomId} {userId} {start} {end} {title=Concurrent Booking}';

    protected $description = 'Attempt a booking creation, used for concurrency and race condition testing.';

    public function handle(RoomBookingService $bookingService): int
    {
        $roomId = (int) $this->argument('roomId');
        $userId = (int) $this->argument('userId');
        $start = $this->argument('start');
        $end = $this->argument('end');
        $title = $this->argument('title');

        try {
            $timeRange = new TimeRange($start, $end);
            $dto = new BookingDTO($roomId, $userId, $title, $timeRange);

            $booking = $bookingService->createBooking($dto);
            $this->info("SUCCESS: Booking created with ID {$booking->id}");

            return 0;
        } catch (ScheduleConflictException $e) {
            $this->error("CONFLICT: {$e->getMessage()}");

            return 10;
        } catch (\Throwable $e) {
            $this->error("ERROR: {$e->getMessage()}");

            return 1;
        }
    }
}
