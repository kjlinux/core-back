<?php

namespace App\Providers;

use App\Models\BiometricDevice;
use App\Models\FeelbackDevice;
use App\Models\RfidDevice;
use App\Observers\DeviceOnlineObserver;
use Illuminate\Auth\Notifications\ResetPassword;
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
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
            return $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        });

        RfidDevice::observe(DeviceOnlineObserver::class);
        BiometricDevice::observe(DeviceOnlineObserver::class);
        FeelbackDevice::observe(DeviceOnlineObserver::class);
    }
}
