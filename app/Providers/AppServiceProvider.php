<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Plan;
use App\Support\Rates;
use App\Support\TaxRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Per-request lookups of the dated rates and tax rules.
        $this->app->scoped(Rates::class);
        $this->app->scoped(TaxRules::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();

        // The plan is built once per request and thrown away whenever anything is
        // saved, so a page always shows it as the data now stands.
        Event::listen(['eloquent.saved: *', 'eloquent.deleted: *', 'eloquent.restored: *'], fn () => Plan::flush());
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
    }

    /**
     * Company access control: every active user can use the whole app; admins can
     * additionally manage users. A deactivated user is denied everything (enforced
     * by the EnsureAccountIsActive middleware). Admin routes use `can:admin`.
     */
    protected function configureAuthorization(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->is_active && $user->is_admin);
    }
}
