<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\EventRegistration;

class BackfillQrTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example: php artisan qr:backfill
     */
    protected $signature = 'qr:backfill';

    /**
     * The console command description.
     */
    protected $description = 'Backfill qr_token for existing free event registrations without one';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = 0;

        EventRegistration::where('status', 'free')
            ->whereNull('qr_token')
            ->orWhere('qr_token', '')
            ->chunkById(100, function ($registrations) use (&$count) {
                foreach ($registrations as $reg) {
                    $reg->qr_token = Str::random(40);
                    $reg->save();
                    $count++;
                }
            });

        $this->info("✅ Backfilled {$count} free registrations with qr_token.");

        return Command::SUCCESS;
    }
}
