<?php

declare(strict_types=1);

namespace URLCV\FocusPlanner\Laravel;

use Illuminate\Support\ServiceProvider;

class FocusPlannerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'focus-planner');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
    }
}
