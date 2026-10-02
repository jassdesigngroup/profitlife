<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\Permission as P;
use App\Domain\Identity\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea el catálogo de permisos y los roles con su matriz por defecto.
 *
 * Es idempotente y seguro en producción:
 * - Super Admin siempre queda con todos los permisos.
 * - Un rol nuevo recibe su matriz por defecto completa.
 * - Un rol existente conserva los cambios hechos desde el panel; solo
 *   recibe los permisos que se acaban de crear en esta ejecución, si su
 *   matriz por defecto los incluye.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            $created = [];

            foreach (P::cases() as $permission) {
                $model = Permission::findOrCreate($permission->value, 'web');

                if ($model->wasRecentlyCreated) {
                    $created[] = $permission->value;
                }
            }

            foreach (RoleName::cases() as $roleName) {
                $role = Role::findOrCreate($roleName->value, 'web');
                $defaults = array_map(fn (P $p) => $p->value, self::defaultPermissions($roleName));

                if ($roleName === RoleName::SuperAdmin || $role->wasRecentlyCreated) {
                    $role->syncPermissions($defaults);

                    continue;
                }

                $new = array_values(array_intersect($defaults, $created));

                if ($new !== []) {
                    $role->givePermissionTo($new);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Matriz por defecto. Recepción y Gerente de sede no tienen ningún
     * permiso clínico; el Administrador tampoco mientras no se decida lo
     * contrario (ERD, sección 15).
     *
     * @return list<P>
     */
    public static function defaultPermissions(RoleName $role): array
    {
        $base = [P::AdminAccess, P::DashboardView, P::LocationsView, P::RoomsView];

        return match ($role) {
            RoleName::SuperAdmin => P::cases(),

            RoleName::Admin => array_values(array_filter(
                P::cases(),
                fn (P $p) => ! $p->isClinical() && $p !== P::UsersManagePermissions,
            )),

            RoleName::LocationManager => [
                ...$base,
                P::LocationsUpdate, P::LocationsManageHours, P::LocationsManageClosures,
                P::RoomsCreate, P::RoomsUpdate, P::RoomsDelete,
                P::StaffView, P::StaffCreate, P::StaffUpdate, P::StaffToggleStatus, P::StaffInvite,
                P::UsersManageRoles,
                P::MembersView, P::MembersCreate, P::MembersUpdate, P::MembersExport,
                P::MembershipsView, P::MembershipsCreate, P::MembershipsUpdate, P::MembershipsFreeze, P::MembershipsCancel,
                P::CheckInsView, P::CheckInsCreate, P::CheckInsOverride,
                P::AppointmentsView, P::AppointmentsViewAll, P::AppointmentsCreate, P::AppointmentsUpdate, P::AppointmentsCancel,
                P::TrainingView,
                P::PaymentsView, P::PaymentsCreate, P::PaymentsRefund, P::PaymentsVoid, P::PaymentsDiscount,
                P::ReportsView, P::ReportsExport,
            ],

            RoleName::Reception => [
                ...$base,
                P::StaffView,
                P::MembersView, P::MembersCreate, P::MembersUpdate,
                P::MembershipsView, P::MembershipsCreate,
                P::CheckInsView, P::CheckInsCreate,
                P::AppointmentsView, P::AppointmentsViewAll, P::AppointmentsCreate, P::AppointmentsUpdate, P::AppointmentsCancel,
                P::PaymentsView, P::PaymentsCreate,
            ],

            RoleName::Physiotherapist => [
                ...$base,
                P::MembersView,
                P::CheckInsView,
                P::AppointmentsView, P::AppointmentsCreate, P::AppointmentsUpdate, P::AppointmentsCancel,
                P::ClinicalNotesView, P::ClinicalNotesCreate, P::ClinicalNotesUpdate, P::ClinicalNotesSign,
                P::AssessmentsView, P::AssessmentsCreate, P::AssessmentsUpdate,
                P::TrainingView, P::TrainingCreate, P::TrainingUpdate,
            ],

            RoleName::Trainer => [
                ...$base,
                P::MembersView,
                P::CheckInsView,
                P::AppointmentsView, P::AppointmentsCreate, P::AppointmentsUpdate, P::AppointmentsCancel,
                P::AssessmentsView, P::AssessmentsCreate, P::AssessmentsUpdate,
                P::TrainingView, P::TrainingCreate, P::TrainingUpdate, P::TrainingDelete,
            ],

            // El portal de clientes llega en fases posteriores; sin acceso al panel.
            RoleName::Member => [],
        };
    }
}
