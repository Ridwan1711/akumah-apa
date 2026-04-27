<?php

namespace App\Providers;

use App\Models\Diniyyah\SchoolClass;
use App\Models\Diniyyah\Subject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
        Route::bind('diniyah_class', fn ($value) => SchoolClass::whereKey($value)->firstOrFail());
        Route::bind('kitab_subject', fn ($value) => Subject::whereKey($value)->firstOrFail());
        Route::bind('teachingAssignment', fn ($value) => \App\Models\Diniyyah\TeacherAssignment::whereKey($value)->firstOrFail());

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
    }
}
