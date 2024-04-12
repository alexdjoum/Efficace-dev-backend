<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait CustomLogsActivity
{
    use LogsActivity;


    public function getActivitylogOptions(): LogOptions
    {
        $event = [
            "created" => "crée",
            "updated" => "modifié",
            "deleted" => "supprimé",
        ];
        return LogOptions::defaults()
            ->setDescriptionForEvent(fn (string $eventName) => "Ce model a été {$event[$eventName]}")
            ->useLogName($this->getTable())
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
