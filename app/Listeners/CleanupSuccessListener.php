<?php

namespace App\Listeners;

use App\Models\Backup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Backup\Events\CleanupWasSuccessful;

class CleanupSuccessListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CleanupWasSuccessful $event): void
    {
        $destination = $event->backupDestination;
        // delete backups DB record that the corresponding backup was deleted
        Backup::all()->each(function ($backup) use ($destination) {
            if ($destination->backups()->where("path", $backup->path())->isEmpty()) {
                $backup->delete();
            }
        });
    }
}