<?php

namespace App\Providers;

use App\Domain\Audit\Listeners\AuthenticationActivitySubscriber;
use App\Domain\Audit\Policies\ActivityPolicy;
use App\Domain\Billing\Events\InvoiceSettled;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\CheckIns\Models\CheckIn;
use App\Domain\CheckIns\Models\KioskDevice;
use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Domain\Consents\Models\Consent;
use App\Domain\Consents\Models\ConsentTemplate;
use App\Domain\Documents\Models\Document;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\RolePolicy;
use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use App\Domain\Locations\Models\LocationHour;
use App\Domain\Locations\Models\Room;
use App\Domain\Members\Models\EmergencyContact;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberNote;
use App\Domain\Memberships\Listeners\ReactivateMembershipOnPayment;
use App\Domain\Memberships\Models\Membership;
use App\Domain\Memberships\Models\MembershipPlan;
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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Kiosco: por dispositivo; los intentos de PIN tienen su propio límite.
        RateLimiter::for('kiosk', fn (Request $request) => Limit::perMinute(60)->by('kiosk:'.($request->user()?->getKey() ?? $request->ip())));

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
            'member' => Member::class,
            'emergency_contact' => EmergencyContact::class,
            'member_note' => MemberNote::class,
            'document' => Document::class,
            'consent_template' => ConsentTemplate::class,
            'consent' => Consent::class,
            'membership_plan' => MembershipPlan::class,
            'membership' => Membership::class,
            'invoice' => Invoice::class,
            'payment' => Payment::class,
            'kiosk_device' => KioskDevice::class,
            'member_access_credential' => MemberAccessCredential::class,
            'check_in' => CheckIn::class,
            'role' => Role::class,
            'permission' => Permission::class,
        ]);

        Event::subscribe(AuthenticationActivitySubscriber::class);
        Event::subscribe(RecordNotificationLog::class);
        Event::listen(InvoiceSettled::class, ReactivateMembershipOnPayment::class);

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
