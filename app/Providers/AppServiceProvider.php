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
use Illuminate\Support\ServiceProvider;
use App\Observers\PartOfBuildingObserver;
use App\Observers\BuildingFinanceObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        Product::observe(ProductObserver::class);
        Property::observe(PropertyObserver::class);
        Land::observe(LandObserver::class);
        Fragment::observe(FragmentObserver::class);
        VideoLand::observe(VideoLandObserver::class);
        BuildingFinance::observe(BuildingFinanceObserver::class);
        PartOfBuilding::observe(PartOfBuildingObserver::class);
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '256M');
    }
}
