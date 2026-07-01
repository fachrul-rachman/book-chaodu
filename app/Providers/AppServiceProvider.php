<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        RateLimiter::for('booking-submit', function (Request $request): Limit {
            $key = implode('|', [
                $request->ip(),
                (string) $request->input('customer_email'),
                (string) $request->input('customer_phone_local'),
            ]);

            return Limit::perMinutes(
                max(1, (int) ceil((int) config('phase3.submit_rate_limit_decay_seconds') / 60)),
                max(1, (int) config('phase3.submit_rate_limit_max_attempts')),
            )->by($key);
        });

        RateLimiter::for('public-ocr', function (Request $request): Limit {
            return Limit::perMinutes(
                max(1, (int) ceil((int) config('phase4.ocr_rate_limit_decay_seconds') / 60)),
                max(1, (int) config('phase4.ocr_rate_limit_max_attempts')),
            )->by((string) $request->ip());
        });

        RateLimiter::for('virtual-account-reserve', function (Request $request): Limit {
            $key = implode('|', [
                (string) $request->ip(),
                (string) $request->input('idempotency_key'),
            ]);

            return Limit::perMinutes(
                max(1, (int) ceil((int) config('phase3.virtual_account_rate_limit_decay_seconds') / 60)),
                max(1, (int) config('phase3.virtual_account_rate_limit_max_attempts')),
            )->by($key);
        });
    }
}
