<?php

namespace App\Providers;

use App\Services\MenuResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
    public function boot(MenuResolver $menu): void
    {
        // Feed the data-driven sidebar with the current user's visible items.
        View::composer('layouts.sidebar', function ($view) use ($menu) {
            $view->with(
                'menuItems',
                Auth::check() ? $menu->visibleItems(Auth::user()) : collect(),
            );
        });
    }
}
