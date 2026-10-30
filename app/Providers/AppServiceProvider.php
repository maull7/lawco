<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
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
        Blade::directive('formatText', function (string $expression): string {
            return "<?php echo \App\Helpers\TextFormatter::paragraphHtml({$expression}); ?>";
        });

        Gate::define('access-legal-decisions', fn (User $user): bool => $user->hasPermission('access_legal_decisions'));
    }
}
