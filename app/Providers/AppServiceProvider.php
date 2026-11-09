<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Contracts\SmsGateway::class, fn () => match (config('dp.sms_driver')) {
            default => new \App\Services\LogSmsGateway(),
        });
    }

    public function boot(): void
    {
        foreach (array_keys(config('dp.permissions')) as $perm) {
            Gate::define($perm, fn ($user) => $user->hasPermission($perm));
        }

        // البانرات والإعلانات لكل صفحات المتجر (بدون كسر الموقع لو الجدول غير موجود بعد)
        \Illuminate\Support\Facades\View::composer('shop.*', function ($view) {
            $view->with('banners', once(fn () => rescue(fn () => \App\Models\Banner::live()->get()->groupBy('placement'), collect(), false)));
            $view->with('shopCategories', once(fn () => rescue(fn () => \App\Models\Category::orderBy('sort')->orderBy('id')->get(['id', 'slug', 'name_ar']), collect(), false)));
        });

        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perMinute(3)->by('otp:'.$r->input('phone', $r->ip())),
            Limit::perHour(15)->by('otp-ip:'.$r->ip()),
        ]);
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
