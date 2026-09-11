<?php

namespace App\Providers;

use App\Services\Commerce\CartService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One CartService per request. It remembers the cart token it minted,
        // because a queued cookie is not readable until the next request --
        // and both the cart endpoints and CheckoutService need to agree on
        // which cart they are looking at within a single call.
        $this->app->scoped(CartService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // MySQL's 767-byte index limit on utf8mb4.
        Schema::defaultStringLength(191);
    }
}
