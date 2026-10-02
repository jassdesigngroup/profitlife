<?php

namespace App\Providers;

use App\Domain\Audit\Listeners\AuthenticationActivitySubscriber;
use App\Domain\Audit\Policies\ActivityPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\RolePolicy;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use App\Domain\Locations\Models\LocationHour;
use App\Domain\Locations\Models\Room;
use App\Domain\Notifications\Listeners\RecordNotificationLog;
use App\Domain\Notifications\Models\NotificationLog;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Services\Settings;
use App\Domain\Staff\Models\Staff;
use App\Http\Admin\Middleware\EnsureCanAccessAdmin;
use App\Http\Admin\Middleware\EnsureTwoFactorIsConfirmed;
use App\Http\Admin\Middleware\EnsureUserIsActive;
use App\Support\Locations\CurrentLocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->scoped(CurrentLocation::class, fn ($app) => new CurrentLocation($app['session.store']));
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Alias estables para columnas polimórficas (roles, auditoría, notificaciones).
        Relation::enforceMorphMap([
            'user' => User::class,
            'staff' => Staff::class,
            'location' => Location::class,
            'location_hour' => LocationHour::class,
            'location_closure' => LocationClosure::class,
            'room' => Room::class,
            'setting' => Setting::class,
            'notification_log' => NotificationLog::class,
            'role' => Role::class,
            'permission' => Permission::class,
        ]);

        Event::subscribe(AuthenticationActivitySubscriber::class);
        Event::subscribe(RecordNotificationLog::class);

        // Las peticiones de Livewire repiten las barreras de la ruta original.
        Livewire::addPersistentMiddleware([
            EnsureUserIsActive::class,
            EnsureCanAccessAdmin::class,
            EnsureTwoFactorIsConfirmed::class,
        ]);

        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        Password::defaults(function () {
            $rule = Password::min(10)->letters()->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Cada registro de auditoría guarda la IP de origen de la petición.
        Activity::creating(function (Activity $activity) {
            if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
                return;
            }

            $activity->properties = $activity->properties->put('ip', request()->ip());
        });
    }
}
