<?php

namespace App\Providers;

use App\Services\AnthropicClient;
use App\Services\GoogleSpeechClient;
use App\Services\MenuResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AnthropicClient::class, fn () => new AnthropicClient(
            config('services.anthropic.key'),
            (string) config('services.anthropic.model'),
        ));

        $this->app->singleton(GoogleSpeechClient::class, fn () => new GoogleSpeechClient(
            config('services.google_speech.api_key'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(MenuResolver $menu): void
    {
        // Force HTTPS for every generated URL in production (Hostinger serves
        // the app over SSL; this stops mixed-content and insecure form posts).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Feed the data-driven sidebar with the current user's visible items.
        View::composer('layouts.sidebar', function ($view) use ($menu) {
            $view->with(
                'menuItems',
                Auth::check() ? $menu->visibleItems(Auth::user()) : collect(),
            );
        });
    }
}
