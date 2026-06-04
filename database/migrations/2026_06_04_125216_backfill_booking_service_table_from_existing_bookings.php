<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Booking::query()
            ->whereNotNull('service_id')
            ->select(['id', 'service_id'])
            ->chunkById(100, function ($bookings) {
                foreach ($bookings as $booking) {
                    DB::table('booking_service')->updateOrInsert(
                        [
                            'booking_id' => $booking->id,
                            'service_id' => $booking->service_id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }

    public function down(): void
    {
        DB::table('booking_service')->truncate();
    }
};