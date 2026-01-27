<?php

namespace App\Providers;

use App\Models\Land;
use App\Models\Product;
use App\Models\Property;
use App\Models\Fragment;
use App\Models\VideoLand;
use App\Models\PartOfBuilding;
use App\Models\BuildingFinance;
use App\Observers\LandObserver;
use App\Observers\ProductObserver;
use App\Observers\FragmentObserver;
use App\Observers\PropertyObserver;
use App\Observers\VideoLandObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use App\Observers\PartOfBuildingObserver;
use App\Observers\BuildingFinanceObserver;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        BackupWasSuccessful::class => [
            \App\Listeners\BackupSuccessListener::class,
        ],

        CleanupWasSuccessful::class => [
            \App\Listeners\CleanupSuccessListener::class,
        ]
    ];

    /**
     * Register any events for your application.
     */
    public function boot()
    {
        Product::observe(ProductObserver::class);
        Property::observe(PropertyObserver::class);
        BuildingFinance::observe(BuildingFinanceObserver::class);
        PartOfBuilding::observe(PartOfBuildingObserver::class);
        Land::observe(LandObserver::class);
        Fragment::observe(FragmentObserver::class);
        VideoLand::observe(VideoLandObserver::class);
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
