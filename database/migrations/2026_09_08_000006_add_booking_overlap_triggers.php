<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Nice to Have: Storage-layer overlap constraint via Database Triggers.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared("
                CREATE TRIGGER trg_bookings_prevent_overlap_insert
                BEFORE INSERT ON bookings
                FOR EACH ROW
                BEGIN
                    DECLARE overlap_count INT;
                    IF NEW.status = 'confirmed' THEN
                        SELECT COUNT(*) INTO overlap_count
                        FROM bookings
                        WHERE room_id = NEW.room_id
                          AND status = 'confirmed'
                          AND start_time < NEW.end_time
                          AND end_time > NEW.start_time;

                        IF overlap_count > 0 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB_STORAGE_CONSTRAINT: Double booking detected! Room is already booked for this time range.';
                        END IF;
                    END IF;
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_bookings_prevent_overlap_update
                BEFORE UPDATE ON bookings
                FOR EACH ROW
                BEGIN
                    DECLARE overlap_count INT;
                    IF NEW.status = 'confirmed' THEN
                        SELECT COUNT(*) INTO overlap_count
                        FROM bookings
                        WHERE room_id = NEW.room_id
                          AND id <> NEW.id
                          AND status = 'confirmed'
                          AND start_time < NEW.end_time
                          AND end_time > NEW.start_time;

                        IF overlap_count > 0 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB_STORAGE_CONSTRAINT: Double booking detected! Room is already booked for this time range.';
                        END IF;
                    END IF;
                END;
            ");
        } elseif ($driver === 'sqlite') {
            DB::unprepared("
                CREATE TRIGGER trg_bookings_prevent_overlap_insert
                BEFORE INSERT ON bookings
                WHEN NEW.status = 'confirmed'
                BEGIN
                    SELECT RAISE(ABORT, 'DB_STORAGE_CONSTRAINT: Double booking detected! Room is already booked for this time range.')
                    WHERE EXISTS (
                        SELECT 1 FROM bookings
                        WHERE room_id = NEW.room_id
                          AND status = 'confirmed'
                          AND start_time < NEW.end_time
                          AND end_time > NEW.start_time
                    );
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER trg_bookings_prevent_overlap_update
                BEFORE UPDATE ON bookings
                WHEN NEW.status = 'confirmed'
                BEGIN
                    SELECT RAISE(ABORT, 'DB_STORAGE_CONSTRAINT: Double booking detected! Room is already booked for this time range.')
                    WHERE EXISTS (
                        SELECT 1 FROM bookings
                        WHERE room_id = NEW.room_id
                          AND id != NEW.id
                          AND status = 'confirmed'
                          AND start_time < NEW.end_time
                          AND end_time > NEW.start_time
                    );
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_bookings_prevent_overlap_insert;');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_bookings_prevent_overlap_update;');
        }
    }
};
