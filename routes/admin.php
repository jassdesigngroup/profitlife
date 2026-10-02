<?php

use App\Http\Admin\Controllers\DocumentDownloadController;
use App\Http\Admin\Controllers\InvoiceController;
use App\Http\Admin\Controllers\MemberAccessCardController;
use App\Http\Admin\Controllers\MemberPhotoController;
use App\Http\Admin\Controllers\ProfileController;
use App\Http\Admin\Controllers\SecurityController;
use App\Livewire\Admin\Appointments\Agenda;
use App\Livewire\Admin\Audit\AuditIndex;
use App\Livewire\Admin\Billing\PaymentIndex;
use App\Livewire\Admin\CheckIns\CheckInIndex;
use App\Livewire\Admin\Consents\ConsentTemplateForm;
use App\Livewire\Admin\Consents\ConsentTemplateIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Locations\LocationForm;
use App\Livewire\Admin\Locations\LocationIndex;
use App\Livewire\Admin\Locations\LocationShow;
use App\Livewire\Admin\Members\MemberForm;
use App\Livewire\Admin\Members\MemberIndex;
use App\Livewire\Admin\Members\MemberShow;
use App\Livewire\Admin\Memberships\MembershipIndex;
use App\Livewire\Admin\Plans\PlanForm;
use App\Livewire\Admin\Plans\PlanIndex;
use App\Livewire\Admin\Roles\RoleEdit;
use App\Livewire\Admin\Roles\RoleIndex;
use App\Livewire\Admin\Services\ServiceForm;
use App\Livewire\Admin\Services\ServiceIndex;
use App\Livewire\Admin\Settings\SettingsPage;
use App\Livewire\Admin\Staff\StaffAvailability;
use App\Livewire\Admin\Staff\StaffForm;
use App\Livewire\Admin\Staff\StaffIndex;
use Illuminate\Support\Facades\Route;

/*
| Panel administrativo. Las barreras son, en orden: sesión, usuario activo,
| permiso admin.access y 2FA confirmado para los roles que lo exigen. Cada
| pantalla y cada acción vuelven a autorizar con su Policy.
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'active', 'admin.access'])
    ->group(function () {
        // Accesibles sin 2FA para poder configurarlo.
        Route::get('/perfil', ProfileController::class)->name('profile');
        Route::get('/perfil/seguridad', SecurityController::class)
            ->middleware('password.confirm')
            ->name('security');

        Route::middleware('two-factor.confirmed')->group(function () {
            Route::livewire('/', Dashboard::class)->name('dashboard');

            Route::livewire('/sedes', LocationIndex::class)->name('locations.index');
            Route::livewire('/sedes/crear', LocationForm::class)->name('locations.create');
            Route::livewire('/sedes/{location}', LocationShow::class)->whereNumber('location')->name('locations.show');
            Route::livewire('/sedes/{location}/editar', LocationForm::class)->whereNumber('location')->name('locations.edit');

            Route::livewire('/clientes', MemberIndex::class)->name('members.index');
            Route::livewire('/clientes/crear', MemberForm::class)->name('members.create');
            Route::livewire('/clientes/{member}', MemberShow::class)->whereNumber('member')->name('members.show');
            Route::livewire('/clientes/{member}/editar', MemberForm::class)->whereNumber('member')->name('members.edit');
            Route::get('/clientes/{member}/foto', MemberPhotoController::class)->whereNumber('member')->name('members.photo');
            Route::get('/clientes/{member}/codigo-acceso', MemberAccessCardController::class)->whereNumber('member')->name('members.access-card');
            Route::get('/documentos/{document}', DocumentDownloadController::class)->whereUuid('document')->name('documents.download');

            Route::livewire('/asistencia', CheckInIndex::class)->name('check-ins.index');
            Route::livewire('/agenda', Agenda::class)->name('appointments.index');
            Route::livewire('/servicios', ServiceIndex::class)->name('services.index');
            Route::livewire('/servicios/crear', ServiceForm::class)->name('services.create');
            Route::livewire('/servicios/{service}/editar', ServiceForm::class)->whereNumber('service')->name('services.edit');
            Route::livewire('/membresias', MembershipIndex::class)->name('memberships.index');
            Route::livewire('/pagos', PaymentIndex::class)->name('payments.index');
            Route::get('/comprobantes/{invoice}', InvoiceController::class)->whereNumber('invoice')->name('invoices.show');
            Route::livewire('/planes', PlanIndex::class)->name('plans.index');
            Route::livewire('/planes/crear', PlanForm::class)->name('plans.create');
            Route::livewire('/planes/{plan}/editar', PlanForm::class)->whereNumber('plan')->name('plans.edit');
            Route::livewire('/ajustes', SettingsPage::class)->name('settings');

            Route::livewire('/staff', StaffIndex::class)->name('staff.index');
            Route::livewire('/staff/crear', StaffForm::class)->name('staff.create');
            Route::livewire('/staff/{staff}/editar', StaffForm::class)->whereNumber('staff')->name('staff.edit');
            Route::livewire('/staff/{staff}/disponibilidad', StaffAvailability::class)->whereNumber('staff')->name('staff.availability');

            Route::livewire('/roles', RoleIndex::class)->name('roles.index');
            Route::livewire('/roles/{role}', RoleEdit::class)->whereNumber('role')->name('roles.edit');

            Route::livewire('/consentimientos', ConsentTemplateIndex::class)->name('consents.index');
            Route::livewire('/consentimientos/publicar', ConsentTemplateForm::class)->name('consents.create');

            Route::livewire('/auditoria', AuditIndex::class)->name('audit.index');
        });
    });
