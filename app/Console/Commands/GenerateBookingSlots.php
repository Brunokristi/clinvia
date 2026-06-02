<?php

namespace App\Console\Commands;

use App\Services\BookingSlotGenerator;
use Illuminate\Console\Command;

class GenerateBookingSlots extends Command
{
    protected $signature = 'bookings:generate-slots {--days=60}';

    protected $description = 'Generate booking slots from availability rules';

    public function handle(BookingSlotGenerator $generator): int
    {
        $created = $generator->generate((int) $this->option('days'));

        $this->info("Generated {$created} booking slots.");

        return self::SUCCESS;
    }
}