<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\EmailNotificationService;
use Illuminate\Console\Command;

class SendPendingRequestDigest extends Command
{
    protected $signature = 'requests:send-pending-digest';

    protected $description = 'Send digest of pending appointment requests to branches';

    public function handle(EmailNotificationService $emailNotificationService): int
    {
        $branches = Branch::query()->where('is_active', true)->get();
        $sent = 0;

        foreach ($branches as $branch) {
            $emailNotificationService->dispatch('request.pending_digest', [
                'branch' => $branch,
            ]);

            $sent += 1;
        }

        $this->info("Processed pending digest for {$sent} branches.");

        return self::SUCCESS;
    }
}
