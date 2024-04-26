<?php

namespace App\Listeners;

use App\Models\Backup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Number;
use NumberFormatter;
use Spatie\Backup\Events\BackupWasSuccessful;

class BackupSuccessListener
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
    public function handle(BackupWasSuccessful $event): void
    {
        $destination = $event->backupDestination;
        $backup = $destination->newestBackup();

        // dd(storage_path("app/{$backup->path()}"));

        Backup::query()->create([
            'path' => $backup->path(),
            'date' => $backup->date(),
            'size' => $backup->sizeInBytes(),
            'name' => $destination->backupName(),
            'disk' => $destination->diskName(),
        ]);
    }
}
